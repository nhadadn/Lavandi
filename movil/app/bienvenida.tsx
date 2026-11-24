// La bienvenida (bienvenida-E01): el nombre del producto que dice la configuración (base-E09) y el lema y la
// ilustración del archivo de contenido (bienvenida-E05). Avanza a la primera informativa (bienvenida-E03).
// Sin "Ya tengo cuenta": no tiene cita (C-225).
//
// El aspecto es el de la imagen a01-bienvenida de la Propuesta 04 de los mocks (tarea 1.3, C-230): el nombre, el lema
// y la ilustración al centro, y "Comenzar" fijo abajo, a todo lo ancho. Mientras no llegue la ilustración de EL
// CLIENTE, el archivo de contenido pone en su lugar el logotipo de nombre neutro (C-237). Del mock no entra la cinta
// "Ejemplo para ilustrar".
import { useRouter } from 'expo-router';

import { nombreDeLaApp } from '../src/configuracion/entorno';
import { contenidoDeBienvenida } from '../src/contenido/bienvenida';
import { Boton } from '../src/piezas/Boton';
import { Ilustracion } from '../src/piezas/Ilustracion';
import { Pantalla } from '../src/piezas/Pantalla';
import { Presentacion } from '../src/piezas/Presentacion';

export default function Bienvenida() {
  const router = useRouter();
  const { bienvenida, botones } = contenidoDeBienvenida;

  return (
    <Pantalla
      testID="pantalla-inicial"
      justificar="center"
      acciones={<Boton onPress={() => router.push('/informativa/1')}>{botones.comenzar}</Boton>}
    >
      <Presentacion titulo={nombreDeLaApp()} texto={bienvenida.lema} tamano="mayor" />
      <Ilustracion fuente={bienvenida.ilustracion} />
    </Pantalla>
  );
}
