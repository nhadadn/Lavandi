// estados-E17: la lavandería marca Recolectado por la API, con su token, y se anota la hora (cambio-en-60-segundos.yaml).
// Corre dentro de Maestro (runScript): http, json y output son de Maestro, y API_URL, PEDIDO, LAVANDERIA_CORREO y
// LAVANDERIA_CONTRASENA llegan como variables del flujo.
//
// Primero abre la sesión de la lavandería, para que el inicio de sesión no cuente en el tiempo. Luego anota la hora
// justo antes de mandar el botón (POST /api/lavanderia/pedidos/{id_publico}/estado, estados-E03): se cuenta desde que
// sale la petición, así que el tiempo medido incluye lo que tarda el servidor en guardar el cambio y nunca se queda
// corto. El cuerpo va en un bloque para que sus nombres no choquen con los de otro script del flujo.
/* global http, json, output, API_URL, PEDIDO, LAVANDERIA_CORREO, LAVANDERIA_CONTRASENA */
{
  const sesion = http.post(API_URL + '/sesion', {
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ correo: LAVANDERIA_CORREO, password: LAVANDERIA_CONTRASENA }),
  });
  if (sesion.status !== 200) {
    throw new Error('La lavandería no pudo iniciar sesión: ' + sesion.status + ' ' + sesion.body);
  }
  output.tokenDeLaLavanderia = json(sesion.body).token;

  const marcadoEn = Date.now();
  const respuesta = http.post(API_URL + '/lavanderia/pedidos/' + PEDIDO + '/estado', {
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Authorization: 'Bearer ' + output.tokenDeLaLavanderia,
    },
    body: JSON.stringify({ estado: 'recolectado' }),
  });
  const respondioEn = Date.now();

  if (respuesta.status !== 200) {
    throw new Error('La API no guardó Recolectado: ' + respuesta.status + ' ' + respuesta.body);
  }
  const estado = json(respuesta.body).pedido.estado.codigo;
  if (estado !== 'recolectado') {
    throw new Error('La API dejó el pedido en ' + estado + ', no en recolectado.');
  }

  output.marcadoEn = marcadoEn;
  console.log(
    'CRONOMETRO marcado pedido=' +
      PEDIDO +
      ' marcado_en=' +
      new Date(marcadoEn).toISOString() +
      ' respuesta_ms=' +
      (respondioEn - marcadoEn),
  );
}
