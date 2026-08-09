# HTTPS local para Geolocation

La API de Geolocation del navegador sólo está disponible en contextos seguros. `http://localhost` y, según el navegador, direcciones loopback pueden recibir una excepción de desarrollo; un dominio personalizado como `http://cliente-piloto.zigo.local:8000` no es un contexto seguro y no debe depender de esa excepción.

La opción recomendada para probar POD localmente es servir el tenant por HTTPS con un certificado local confiable:

1. Instalar una autoridad certificadora local, por ejemplo `mkcert`, y confiarla en el sistema/navegador de pruebas.
2. Generar un certificado que incluya `cliente-piloto.zigo.local` en sus SAN.
3. Configurar un VirtualHost SSL de Apache/XAMPP para ese host, apuntando el `DocumentRoot` a `public/`, y registrar el dominio en el archivo `hosts` local.
4. Configurar `APP_URL` y el dominio del tenant con la URL HTTPS correspondiente, limpiar la caché de configuración y verificar en consola que `window.isSecureContext === true`.
5. Probar permiso concedido, permiso denegado y timeout desde el dispositivo/navegador objetivo.

Como alternativa temporal de desarrollo puede usarse un túnel HTTPS hacia el servidor local, siempre que se configure el host resultante como dominio local del tenant y no se expongan datos reales. La excepción de navegador para tratar orígenes inseguros como seguros sólo debe usarse en un perfil de navegador aislado; no forma parte de la aplicación ni debe configurarse en Stage/PRD.

No existe bypass de GPS en la aplicación: no se generan coordenadas, no se acepta `0,0` como sustituto y la validación productiva continúa exigiendo latitud, longitud y precisión para completar una entrega.
