# IA-09 — Simulator, Readiness y Publish V1

## Alcance

El Simulator permite que owner/admin del tenant pruebe una versión exacta de un Agent contra su AgentContractVersion exacta, revise evidencia determinística y publique manualmente mediante el lifecycle existente. Publicar hace que `Agent.current_published_version_id` apunte a la versión publicada y la vuelve elegible para canales futuros; no crea ni expone un canal.

## Modo de ejecución y firewall

`AiExecutionMode` es autoridad exclusiva del servidor: `live` o `simulation`. Cada llamada reutilizada del Runtime crea un `RuntimeRun` marcado como `simulation`. El modo nunca se acepta desde mensajes, salida del modelo ni argumentos de Action.

Simulation no invoca `ActionHandler`, `ActionExecutor` ni `ConfirmActionRunService` y no crea `ActionRun`. Tampoco llama a los servicios operacionales de Lead, Outcome o Handoff. Por ello no crea `Lead`, `OutcomeEvent`, `HumanHandoff`, `LocalShipment`, guía, `TenantOperation` ni llamada a proveedor logístico. Esto aplica por igual a Actions READ y WRITE.

Knowledge retrieval sí reutiliza el retriever existente, con los límites de tenant, Agent, AgentVersion/Contract y fuentes vinculadas ya aplicados por esa infraestructura. El transcript vive en `SimulationCaseRun`; no se crea Conversation operacional.

## Escenarios y evidencia

`SimulationScenario.definition` está cifrada y contiene hasta 10 turnos por defecto (configuración interna `ai.simulator.max_turns_per_scenario`), fixtures de resultados de Actions, la política booleana server-side `simulate_confirmation` y assertions V1. No existe lenguaje de scripting. Los límites internos también acotan Scenarios por Run, bytes de cada mensaje, bytes JSON canónicos de cada fixture y bytes JSON canónicos del transcript; se miden con `strlen`, por lo que UTF-8 se contabiliza por bytes reales y no por caracteres.

Un `SimulationRun` fija Agent, AgentVersion, AgentContractVersion y el fingerprint SHA-256 canónico del conjunto habilitado. Cada `SimulationCaseRun` cifra el snapshot exacto, transcript, observaciones y trazas de Actions. Editar luego un Scenario no altera evidencia histórica; cambia el fingerprint actual y vuelve stale el Run para readiness.

Las assertions permitidas son `response_completed`, `expected_action_key`, `no_action_expected`, `expected_handoff`, `no_handoff_expected`, `expected_outcome_type`, `no_outcome_expected`, `expected_safe_error_code`, `response_contains` y `response_not_contains`. Son determinísticas, todos los escenarios habilitados bloquean y un Case FAIL hace FAIL el Run.

## Actions simuladas

Cuando el modelo solicita una Action, el Simulator valida Registry, entitlements vigentes, `Contract.allowed_actions` e input schema. Registra una traza simulada, obtiene el fixture del Scenario, valida su output schema y lo entrega a Pass 2 como datos no confiables. Nunca ejecuta el handler.

Para WRITE, `simulate_confirmation=true` únicamente permite continuar la simulación. No confirma ni crea un ActionRun y no ejecuta ninguna operación. Texto malicioso dentro de un fixture sigue siendo `ACTION_RESULT` no confiable: no puede publicar, cambiar execution mode ni solicitar una Action recursiva. La lógica es genérica; ZIGO usa sus Actions registradas (`zigo.quote_shipment`, `zigo.track_shipment`, `zigo.create_shipment_guide`) sin ramas especiales. Tests pueden registrar `example.lookup_record` sin código de producción.

Las observaciones `would_create_lead`, `would_create_outcome` y `would_request_handoff` describen lo que habría ocurrido y mantienen las tablas operacionales sin filas nuevas.

## Readiness y publicación

Readiness es determinístico: READY o NOT_READY. Revalida Agent no retirado, candidata aprobada, contrato exacto aceptado/activo, configuración del modelo, allowlist válida, Actions presentes en Registry, entitlements, al menos un Scenario habilitado, último Run PASS, versión/contrato exactos, fingerprint vigente, todos los Cases PASS y ausencia de errores runtime/policy. No exige Actions, por lo que un Agent FAQ/Knowledge-only puede quedar READY.

READY nunca publica automáticamente. Después de revisión humana, el backend de Publish vuelve a autorizar owner/admin + AI_CORE y abre una transacción corta. El orden es Agent, Contract, ContractVersion, AgentVersion, Tenant/subscriptions/entitlements, Scenarios y evidencia. Toda creación o edición de Scenario pasa por `SimulationScenarioMutationService`, que toma primero el mismo lock del Agent. Con esa barrera compartida, Publish bloquea y relee la autoridad real de entitlements, recalcula readiness con Scenarios, Run y Cases bloqueados, revalida el Registry controlado por código y llama a `AgentVersionLifecycleService`; el lifecycle y `current_published_version_id` se actualizan antes de liberar la transacción. Un Run de V1/C1 no publica V2/C2. La resolución publicada reutiliza `current_published_version_id`; versiones anteriores quedan como evidencia histórica según IA-02B.

Los modelos tenant-scoped, rutas con UUID y FKs compuestas fallan cerrado ante tenant o agregado incorrecto. Definition, snapshot, transcript, observations y action traces están cifrados. Blade escapa el contenido, incluido `<script>alert('IA09-XSS')</script>`. No se registran fixtures, transcript ni salida cruda del modelo.

## Scenario Builder V1 y evidencia de aceptación

Tenant Admin crea y edita escenarios sin enviar una definición JSON. La interfaz ofrece mensajes multi-turn, una selección limitada a Registry ∩ entitlement ∩ `Contract.allowed_actions`, “Ninguna acción”, confirmación simulada solo para WRITE y comprobaciones determinísticas de respuesta, handoff, outcome y contenido. El servidor reconstruye la definición canónica y rechaza campos de autoridad o assertions desconocidas.

Los resultados simulados se capturan propiedad por propiedad usando exclusivamente nombres presentes en `ActionDefinition.output_schema`. Strings, booleanos y números usan controles simples. Una propiedad compleja array/object acepta como fallback el valor estructurado de esa propiedad, nunca la definición completa ni un schema editable. El backend coerciona, limita y vuelve a validar el resultado con `ActionSchemaValidator`.

Los límites se validan al guardar el Scenario y nuevamente al ejecutar. Un mensaje oversized falla antes de ModelGateway; un fixture oversized no llega a Pass 2. Cada entrada de transcript se prueba contra el límite antes de anexarla: si un resultado del modelo o la acumulación multi-turn excede el máximo, el Case queda FAIL con código seguro y sólo persiste el transcript anterior que todavía estaba dentro del límite. Ningún contenido oversized se registra en logs ni en columnas plaintext.

La evidencia end-to-end usa `ModelGateway` fake, `Http::preventStrayRequests()` y conteos antes/después para demostrar cero `ActionRun`, Lead, OutcomeEvent, HumanHandoff, TenantOperation y LocalShipment en quote, tracking, guide WRITE y una Action genérica. Las llamadas de modelo se comprueban con nivel de transacción cero. SQLite cubre doble submit secuencial y el orden de locks; no reproduce el locking concurrente de MySQL y no se utiliza la base normal.

## Límites y exclusiones V1

No se agregó Webchat, WhatsApp, endpoint anónimo, URL pública, token público, widget, embed, Billing, Usage, Credits, Marketplace, Generic HTTP Action, código arbitrario del cliente, nueva Action logística, Campaign, Workspace, Project, Batch, Queue, Job ni IA-10. Las llamadas al ModelGateway ocurren fuera de transacciones DB. Pruebas deben usar gateway fake, `Http::preventStrayRequests()` y nunca OpenAI, Xperta, Estafeta, proveedor real ni MySQL/base normal.
