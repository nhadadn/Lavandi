// Solo para la vista previa en el navegador: el almacén cifrado del teléfono no existe en la web.
const leer = (k) => Promise.resolve(window.localStorage.getItem(k));
const escribir = (k, v) => Promise.resolve(window.localStorage.setItem(k, v));
const borrar = (k) => Promise.resolve(window.localStorage.removeItem(k));
module.exports = { getItemAsync: leer, setItemAsync: escribir, deleteItemAsync: borrar };
