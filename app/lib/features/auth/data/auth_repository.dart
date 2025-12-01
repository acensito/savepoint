import 'package:pocketbase/pocketbase.dart';

class AuthRepository {
  final PocketBase _pb;

  AuthRepository(this._pb);

  // Método para iniciar sesión
  Future<void> login(String email, String password) async {
    try {
      await _pb.collection('users').authWithPassword(email, password);
    } catch (e) {
      // Si falla, lanzamos un error limpio
      throw Exception("Error al iniciar sesión: Comprueba tus datos.");
    }
  }

  // Método para cerrar sesión
  void logout() {
    _pb.authStore.clear();
  }

  // Método para saber si ya estamos logueados
  bool get isLoggedIn => _pb.authStore.isValid;
}