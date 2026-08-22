# IA-05A — Agent response runtime

## Alcance

IA-05A agrega una simulación administrativa de respuesta para Agent Draft y Testing. Recupera únicamente conocimiento Ready vinculado, realiza como máximo una llamada al `ModelGateway` y muestra respuesta propuesta, confianza, handoff y fuentes. No publica el agente, no crea conversaciones y no ejecuta acciones.

## Contrato y adapter

`ModelGateway` recibe y devuelve DTOs provider-neutral. El adapter inicial usa exclusivamente `https://api.openai.com/v1/responses`, el modelo allowlisted `gpt-5.6-luna`, Structured Outputs con JSON Schema estricto, `reasoning.effort=none`, `store=false` y máximo 600 output tokens. No envía tools, búsquedas, archivos, metadata tenant ni estado conversacional. No realiza retries HTTP automáticos.

La credencial y el proyecto proceden solamente de configuración. El endpoint no es configurable desde HTTP y debe coincidir exactamente con el host oficial. `AI_DEFAULT_PROVIDER` permanece vacío por defecto, por lo que el runtime falla cerrado hasta configurarse explícitamente.

## Controles de respuesta

QUESTION y KNOWLEDGE viajan como datos estructurados separados de instrucciones. La política transitoria indica que son datos no confiables y prohíbe obedecer instrucciones incrustadas, revelar reglas, inventar información, afirmar acciones o usar citas desconocidas. La salida se vuelve a validar localmente: claves exactas, respuesta acotada, cita allowlisted, confianza y handoff consistentes.

## Runtime Run

`ai_runtime_runs` conserva únicamente auditoría técnica mínima: agregado y actor tenant-scoped, estado, proveedor/modelo internos, unidades, snapshot de precios, estimación entera en microUSD, citas, confianza, handoff, latencia y error seguro. Nunca almacena pregunta, respuesta, instrucciones, chunks, cuerpos HTTP, credenciales o trazas. No descuenta wallet ni genera cargos.

Precios internos iniciales por millón de unidades: input 200000 microUSD, cached input 20000 y output 1200000. El snapshot se fija en cada run completado.

## Límites

El retrieval lexical continúa siendo determinista: topK máximo 6, extracto máximo 1,200 caracteres y contexto total máximo 12,000. No hay embeddings, vector database, RAG persistente, streaming, Webchat, WhatsApp, tools ni publicación. La validación concurrente real de consumo sobre MySQL queda como prueba futura; IA-05A no implementa cobro.
