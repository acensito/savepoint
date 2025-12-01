import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';
import 'package:savepoint/core/providers/core_providers.dart';
import 'package:savepoint/features/auth/data/auth_repository.dart';

// 1. Proveedor del Repositorio (para que Riverpod sepa cómo crearlo)
final authRepositoryProvider = Provider<AuthRepository>((ref) {
  final pb = ref.watch(pocketBaseProvider);
  return AuthRepository(pb);
});

// 2. Controlador de Estado del Login (Gestiona: Inicial, Cargando, Éxito, Error)
class LoginController extends StateNotifier<AsyncValue<void>> {
  final AuthRepository _repository;

  LoginController(this._repository) : super(const AsyncValue.data(null));

  Future<void> login(String email, String password) async {
    // 1. Ponemos el estado en "Cargando" (para mostrar spinner)
    state = const AsyncValue.loading();

    // 2. Intentamos loguear
    state = await AsyncValue.guard(() => _repository.login(email, password));
  }
  
  void logout() {
    _repository.logout();
  }
}

// 3. El Provider que usaremos en la Pantalla
final loginControllerProvider = StateNotifierProvider<LoginController, AsyncValue<void>>((ref) {
  final repository = ref.watch(authRepositoryProvider);
  return LoginController(repository);
});