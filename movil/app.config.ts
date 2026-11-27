// Configuración de Expo: una sola app para iPhone y Android (ADR-0002).
// Lo que identifica a la app sale del entorno: en local, de .env (copia de .env.example); al compilar en la nube,
// de las variables de EAS. Ni la marca, ni el dominio, ni el identificador se escriben aquí (CLAUDE.md §5).
import { existsSync } from 'node:fs';
import path from 'node:path';

import type { ConfigContext, ExpoConfig } from 'expo/config';

// El texto de para qué se usa la ubicación (cercanas-E03): el mismo en el diálogo de iPhone y en la pantalla. Es JSON
// porque la configuración no puede importar TypeScript de src/.
import permisoDeUbicacion from './src/cercanas/permiso-de-ubicacion.json';
// Los textos de para qué se usan la cámara y las fotos (alta-lavanderias-E03 y perfil-usuario-E03), en JSON por la
// misma razón.
import permisosDeLaFoto from './src/alta-lavanderias/permisos-de-la-foto.json';

// Versiones mínimas (ADR-0009): iOS 18 y Android 11, que es la API 30. El SDK 58 pide menos, así que mandan estas.
const IOS_MINIMO = '18.0';
const ANDROID_API_MINIMA = 30;

const VARIABLES = ['EXPO_PUBLIC_NOMBRE_APP', 'EXPO_PUBLIC_API_URL', 'APP_IDENTIFIER'] as const;
type Variable = (typeof VARIABLES)[number];

// Sin sus variables, la configuración falla en vez de inventar un valor.
function leerEntorno(): Record<Variable, string> {
  const valores = VARIABLES.map((variable) => [variable, process.env[variable]?.trim() ?? ''] as const);
  const faltan = valores.filter(([, valor]) => valor === '').map(([variable]) => variable);
  if (faltan.length > 0) {
    throw new Error(
      `Faltan variables de entorno de la app: ${faltan.join(', ')}. En local, copia .env.example a .env.`,
    );
  }
  return Object.fromEntries(valores) as Record<Variable, string>;
}

// Google en la app (ADR-0005): el complemento de react-native-nitro-google-signin registra en iPhone el esquema
// invertido del cliente OAuth de iPhone, sin archivos de Firebase. Android no lo necesita: el cliente web va en
// configure(). Es opcional mientras no exista el cliente de iPhone (DD-069): sin él, la configuración no inventa un
// esquema y Google no funciona en iPhone. El ID sale del entorno (ADR-0012).
const SUFIJO_DE_CLIENTE_DE_GOOGLE = '.apps.googleusercontent.com';
const PREFIJO_DE_ESQUEMA_DE_GOOGLE = 'com.googleusercontent.apps.';

function complementoDeGoogle(): [string, { iosUrlScheme: string }] | null {
  const clienteIos = process.env.EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID?.trim();
  if (!clienteIos) {
    return null;
  }
  const sinSufijo = clienteIos.endsWith(SUFIJO_DE_CLIENTE_DE_GOOGLE)
    ? clienteIos.slice(0, -SUFIJO_DE_CLIENTE_DE_GOOGLE.length)
    : clienteIos;
  return ['react-native-nitro-google-signin', { iosUrlScheme: `${PREFIJO_DE_ESQUEMA_DE_GOOGLE}${sinSufijo}` }];
}

// La llave de Google Maps para Android (ADR-0009 y ADR-0012, cercanas-E02): de prueba en el .env local y de los
// secretos de EAS al compilar; nunca se escribe aquí. No es EXPO_PUBLIC_: va al manifiesto de Android y no al código
// de la app. Es opcional: sin ella, el complemento de react-native-maps no deja llave en el manifiesto, y como el SDK de
// Google Maps cierra la app al crear un mapa sin llave, extra.mapaDeGoogleEnAndroid le dice a la app que no lo monte
// (src/cercanas/mapa.ts). iPhone no lleva llave: usa el mapa de Apple.
function llaveDeGoogleMapsParaAndroid(): string | undefined {
  return process.env.GOOGLE_MAPS_ANDROID_API_KEY?.trim() || undefined;
}

// Los avisos en Android (ADR-0008 y su adenda; ADR-0012; avisos-E03): el google-services.json del proyecto de Firebase
// del entorno, fuera del repositorio. En local, su ruta en GOOGLE_SERVICES_JSON del .env; al compilar en la nube, la
// variable de archivo de EAS. Es opcional mientras no existan las llaves (DD-072): sin ella, la configuración sale igual
// que antes, sin el archivo ni el permiso de avisos, y extra.firebaseEnAndroid le dice a la app que no cargue la
// mensajería (src/avisos/firebase.ts). Con ella, Expo copia el archivo al proyecto de Android y aplica el complemento de
// Gradle de Google, y entonces react-native.config.js enlaza los módulos nativos de Firebase (firebase-nativo.js).
// El complemento de @react-native-firebase/app no se lista: en Android hace lo mismo que Expo ya hace con
// googleServicesFile, y su parte de iPhone exige el GoogleService-Info.plist, que espera avisos-E18 (tarea 5.2).
const PERMISO_DE_AVISOS = 'android.permission.POST_NOTIFICATIONS';

function archivoDeFirebaseParaAndroid(raizDelProyecto: string): string | undefined {
  const ruta = process.env.GOOGLE_SERVICES_JSON?.trim();
  if (!ruta) {
    return undefined;
  }
  const completa = path.resolve(raizDelProyecto, ruta);
  if (!existsSync(completa)) {
    throw new Error(`La variable GOOGLE_SERVICES_JSON apunta a ${completa}, que no existe.`);
  }
  return ruta;
}

// Los perfiles de compilación de EAS (eas.json; ADR-0002 y ADR-0012): `preview` para el entorno de prueba y `production`
// para las tiendas. Cada uno deja su nombre en PERFIL_DE_COMPILACION, con el "env" de eas.json, que EAS aplica al leer
// esta configuración en la máquina y en la nube. Sin él, la compilación es la de la máquina: el APK local
// (herramientas/apk-local/compilar.ps1) o Expo Go. Un nombre que no es de eas.json detiene la configuración, en vez de
// tratarse como local.
const PERFILES_DE_EAS = ['preview', 'production'] as const;
type PerfilDeEas = (typeof PERFILES_DE_EAS)[number];

function perfilDeCompilacion(): PerfilDeEas | null {
  const perfil = process.env.PERFIL_DE_COMPILACION?.trim();
  if (!perfil) {
    return null;
  }
  const conocido = PERFILES_DE_EAS.find((nombre) => nombre === perfil);
  if (!conocido) {
    throw new Error(
      `PERFIL_DE_COMPILACION vale "${perfil}", que no es un perfil de eas.json: ${PERFILES_DE_EAS.join(' o ')}.`,
    );
  }
  return conocido;
}

// Las compilaciones de prueba y de producción solo hablan por https (publicacion-E06; SEG-1;
// NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3): la API del entorno tiene que ser https, o la configuración se detiene. En
// local no se revisa: el servidor de la máquina responde por http.
function exigirApiCifrada(perfil: PerfilDeEas, direccion: string): void {
  let protocolo = '';
  try {
    protocolo = new URL(direccion).protocol;
  } catch {
    // Una dirección que no se puede leer tampoco es https.
  }
  if (protocolo !== 'https:') {
    throw new Error(`El perfil ${perfil} solo habla por https: EXPO_PUBLIC_API_URL tiene que ser una dirección https.`);
  }
}

// El identificador de la app no se puede cambiar después de publicar, y el definitivo espera la marca (DD-023 y DD-088).
// Mientras el entorno de producción traiga el marcador {{MARCA}} en APP_IDENTIFIER, la compilación de producción se
// detiene antes de empezar (publicacion-E08). En local y en preview no se revisa: ahí el identificador es uno neutro de
// desarrollo.
const MARCADOR_DE_LA_MARCA = /\{\{\s*marca\s*\}\}/i;

function exigirIdentificadorDefinitivo(perfil: PerfilDeEas | null, identificador: string): void {
  if (perfil === 'production' && MARCADOR_DE_LA_MARCA.test(identificador)) {
    throw new Error(
      'Falta el identificador de la app: APP_IDENTIFIER trae el marcador de la marca. El definitivo espera DD-088 y no ' +
        'se puede cambiar después de publicar.',
    );
  }
}

// Sin tráfico sin cifrar en las compilaciones de EAS (publicacion-E06): en Android, usesCleartextTraffic en false; en
// iPhone, la seguridad de transporte sin cargas arbitrarias ni a la red local, y sin la excepción que la plantilla de
// Expo deja para la máquina local.
const TRANSPORTE_CIFRADO_EN_IPHONE = { NSAllowsArbitraryLoads: false, NSAllowsLocalNetworking: false };

export default ({ projectRoot }: ConfigContext): ExpoConfig => {
  const entorno = leerEntorno();
  const perfil = perfilDeCompilacion();
  exigirIdentificadorDefinitivo(perfil, entorno.APP_IDENTIFIER);
  if (perfil) {
    exigirApiCifrada(perfil, entorno.EXPO_PUBLIC_API_URL);
  }
  const google = complementoDeGoogle();
  const llaveDeMapas = llaveDeGoogleMapsParaAndroid();
  const firebase = archivoDeFirebaseParaAndroid(projectRoot);

  return {
    name: entorno.EXPO_PUBLIC_NOMBRE_APP,
    slug: 'movil',
    // El esquema de los enlaces de la app (expo-router, ADR-0017): sin él, expo-linking falla en una compilación de
    // producción. Es el mismo identificador de la app, que sale del entorno.
    scheme: entorno.APP_IDENTIFIER,
    version: '1.0.0',
    orientation: 'portrait',
    // Solo modo claro en la versión 1 (design system).
    userInterfaceStyle: 'light',
    platforms: ['ios', 'android'],
    // El ícono y su fondo son los marcadores neutros de la plantilla hasta que exista la marca
    // (base-tecnica, design.md, pregunta abierta 3).
    icon: './assets/icon.png',
    ios: {
      bundleIdentifier: entorno.APP_IDENTIFIER,
      // Sin iPad: el contrato pide la app para iPhone y Android (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3), y el
      // true era un resto de la plantilla de Expo. Un iPad la puede abrir como app de iPhone.
      supportsTablet: false,
      ...(perfil ? { infoPlist: { NSAppTransportSecurity: TRANSPORTE_CIFRADO_EN_IPHONE } } : {}),
    },
    android: {
      package: entorno.APP_IDENTIFIER,
      adaptiveIcon: {
        backgroundColor: '#E6F4FE',
        foregroundImage: './assets/android-icon-foreground.png',
        backgroundImage: './assets/android-icon-background.png',
        monochromeImage: './assets/android-icon-monochrome.png',
      },
      predictiveBackGestureEnabled: false,
      // La ubicación en segundo plano no se declara (cercanas-E03): se quita del manifiesto final aunque una librería
      // la traiga.
      blockedPermissions: ['android.permission.ACCESS_BACKGROUND_LOCATION'],
      // Con Firebase, el archivo del entorno y el permiso de avisos que Android 13 o superior pide al usuario
      // (avisos-E07); sin él, nada de los dos.
      ...(firebase ? { googleServicesFile: firebase, permissions: [PERMISO_DE_AVISOS] } : {}),
    },
    plugins: [
      // La navegación entre pantallas (ADR-0017).
      'expo-router',
      // El token de la sesión en el almacenamiento cifrado (ADR-0018). La app no usa Face ID, así que no pide el
      // permiso; el complemento además saca ese almacenamiento del respaldo automático de Android.
      ['expo-secure-store', { faceIDPermission: false }],
      [
        'expo-build-properties',
        {
          ios: { deploymentTarget: IOS_MINIMO },
          android: {
            minSdkVersion: ANDROID_API_MINIMA,
            // Las compilaciones de EAS: sin tráfico sin cifrar (publicacion-E06) y sin minificar (publicacion-E09). Con
            // R8, el release se cierra al abrir porque desaparece la base interna de WorkManager, que llega con la
            // ubicación; el APK local ya lo apaga y abre (herramientas/apk-local/compilar.ps1, C-236). Sin perfil no se
            // toca: el APK local sale como antes.
            ...(perfil ? { usesCleartextTraffic: false, enableMinifyInReleaseBuilds: false } : {}),
          },
        },
      ],
      ...(google ? [google] : []),
      // El mapa (ADR-0009): react-native-maps sin proveedor, Apple en iPhone y Google en Android. Solo Android lleva
      // la llave de Google; sin iosGoogleMapsApiKey, iPhone no instala Google Maps.
      ['react-native-maps', llaveDeMapas ? { androidGoogleMapsApiKey: llaveDeMapas } : {}],
      // La ubicación solo mientras se usa la app (cercanas-E03). Sin esta entrada, Expo aplica expo-location con sus
      // valores por omisión: textos en inglés y las llaves de "siempre" en iPhone. Aquí: el texto propio para el uso
      // mientras la app está abierta; sin "siempre", sin segundo plano ni servicio en primer plano en las dos
      // plataformas; sin el sensor de movimiento ni la precisión temporal, que la app no usa.
      [
        'expo-location',
        {
          locationWhenInUsePermission: permisoDeUbicacion.texto,
          locationAlwaysAndWhenInUsePermission: false,
          locationAlwaysPermission: false,
          isIosBackgroundLocationEnabled: false,
          isAndroidBackgroundLocationEnabled: false,
          isAndroidForegroundServiceEnabled: false,
          motionUsagePermission: false,
          locationFullAccuracyPermission: false,
        },
      ],
      // La foto de la lavandería y la de perfil, tomadas o elegidas (alta-lavanderias-E03 y perfil-usuario-E03;
      // ADR-0006). Sin esta entrada, Expo aplica expo-image-picker con sus valores por omisión: textos en inglés y el
      // micrófono, que la app no usa porque no graba video. Aquí: el texto propio de la cámara y de las fotos, y el
      // micrófono quitado en las dos plataformas.
      [
        'expo-image-picker',
        {
          cameraPermission: permisosDeLaFoto.camara,
          photosPermission: permisosDeLaFoto.fotos,
          microphonePermission: false,
        },
      ],
    ],
    extra: {
      mapaDeGoogleEnAndroid: llaveDeMapas !== undefined,
      firebaseEnAndroid: firebase !== undefined,
    },
  };
};
