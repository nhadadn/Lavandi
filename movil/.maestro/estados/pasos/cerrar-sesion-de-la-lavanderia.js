// Al terminar el flujo, pase o falle, cierra la sesión que abrió marcar-recolectado.js (DELETE /api/sesion), para no
// dejar tokens vivos de la lavandería de prueba. Corre dentro de Maestro (onFlowComplete de cambio-en-60-segundos.yaml).
/* global http, output, API_URL */
if (output.tokenDeLaLavanderia) {
  const respuesta = http.request(API_URL + '/sesion', {
    method: 'DELETE',
    headers: { Accept: 'application/json', Authorization: 'Bearer ' + output.tokenDeLaLavanderia },
  });
  console.log('Sesión de la lavandería cerrada: ' + respuesta.status);
  output.tokenDeLaLavanderia = null;
}
