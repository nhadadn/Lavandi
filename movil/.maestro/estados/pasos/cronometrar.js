// estados-E17: cuánto tardó "Recolectado" en verse en el seguimiento desde que la lavandería lo marcó
// (cambio-en-60-segundos.yaml; NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3). Corre dentro de Maestro (runScript),
// justo después de que la pantalla lo mostró: output.marcadoEn lo dejó marcar-recolectado.js.
//
// El tiempo incluye lo que tarda Maestro en leer la pantalla, así que puede pasarse un poco del real, nunca quedarse
// corto. Si pasa de 60 segundos, la prueba falla. El cuerpo va en un bloque para que sus nombres no choquen con los de
// otro script del flujo.
/* global output */
{
  const LIMITE_MS = 60000;

  const vistoEn = Date.now();
  const tardo = vistoEn - output.marcadoEn;
  output.segundos = (tardo / 1000).toFixed(1);

  console.log(
    'CRONOMETRO visto marcado_en=' +
      new Date(output.marcadoEn).toISOString() +
      ' visto_en=' +
      new Date(vistoEn).toISOString() +
      ' tardo_ms=' +
      tardo,
  );

  if (tardo > LIMITE_MS) {
    throw new Error('Recolectado tardó ' + output.segundos + ' s en verse: el contrato pide 60 s o menos.');
  }
}
