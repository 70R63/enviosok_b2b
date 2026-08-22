# IA-04B — Knowledge indexing and retrieval

## Propósito

IA-04B convierte las versiones aprobadas de conocimiento manual y FAQ en fragmentos reproducibles y permite probar su recuperación desde un Agent Draft. La pantalla **Probar búsqueda de conocimiento** muestra únicamente fragmentos relevantes y sus fuentes; no genera respuestas conversacionales.

## Indexación

Cada `KnowledgeSourceVersion` tiene como máximo un índice canónico. La solicitud exige ZIGO AI habilitado, suscripción vigente, `AI_CORE`, Source activa, Version aprobada y actor owner/admin. El trabajo se envía mediante la infraestructura tenant-aware de IA-01 y restaura el `TenantContext` antes de procesar.

El flujo de estados es `pending → processing → ready` o `failed`. Un índice `ready` con el mismo checksum es idempotente. Un índice fallido puede reintentarse. Los únicos códigos persistidos son `invalid_content`, `checksum_mismatch`, `source_archived` e `indexing_failed`; no se guardan trazas ni mensajes arbitrarios.

## Fragmentación determinista

- Manual: conserva párrafos y orden; divide texto largo con un máximo aproximado de 1,200 caracteres Unicode y solapamiento acotado.
- FAQ: conserva pregunta y respuesta, y registra un locator comercial por número de pregunta.
- Cada fragmento tiene secuencia, locator, longitud y checksum canónico reproducibles.

El contenido se trata exclusivamente como datos. No se transforma en prompts, reglas, acciones ni configuración del agente.

## Retrieval lexical

El contrato `KnowledgeRetriever` permite reemplazar en el futuro la implementación local. `LexicalKnowledgeRetriever` normaliza Unicode, compara frase y términos, limita candidatos y devuelve como máximo diez matches ordenados de forma determinista.

La consulta sólo considera conocimiento que esté simultáneamente vinculado a la Agent Version solicitada, listo, con checksum válido y perteneciente a una Source activa del mismo tenant. Excluye otros tenants, Agents y Agent Versions, Sources archivadas e índices pendientes, procesando o fallidos. Un resultado incluye UUID público y nombre de fuente, versión, tipo, locator, extracto limitado y score; nunca IDs internos, checksums, JSON completo, configuración o contrato.

## Seguridad y límites

Las rutas permanecen dentro de Tenant Admin con resolución de tenant, autenticación, suscripción, acceso owner/admin y entitlement `AI_CORE`. Los formularios rechazan campos inesperados e IDs internos. La pregunta y los resultados no se persisten. No existen providers, HTTP saliente, embeddings, base vectorial, RAG generativo, Webchat ni publicación en esta fase.

La serialización real de trabajos y la concurrencia se validan de forma determinista en SQLite. Una prueba de contención concurrente sobre MySQL queda pendiente para una fase de validación de infraestructura; las constraints únicas y los locks constituyen la defensa de producción.
