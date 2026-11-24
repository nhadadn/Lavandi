// Las rutas de la lavandería del socio en el Módulo B (ADR-0017): el registro, que la app abre a la cuenta que todavía
// no tiene lavandería (alta-lavanderias-E03), y el perfil y horarios, que se abre desde los pedidos
// (alta-lavanderias-E19).
import type { Href } from 'expo-router';

export const RUTA_DEL_REGISTRO_DE_LA_LAVANDERIA: Href = '/lavanderia/registro';

export const RUTA_DEL_PERFIL_DE_LA_LAVANDERIA: Href = '/lavanderia/perfil';
