// Solo para la vista previa en el navegador: la librería nativa de Google no existe en la web.
const nada = () => false;
module.exports = {
  GoogleOneTapSignIn: { configure() {}, async checkPlayServices() {}, async signIn() { throw new Error('Google no está en la vista web'); } },
  isCancelledResponse: nada, isErrorWithCode: nada, isNoSavedCredentialFoundResponse: nada, isSuccessResponse: nada,
  statusCodes: {},
};
