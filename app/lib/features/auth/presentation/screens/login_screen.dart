import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:savepoint/features/auth/providers/auth_provider.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  // Controladores para los campos de texto
  final _emailController = TextEditingController();
  final _passController = TextEditingController();

  @override
  void dispose() {
    _emailController.dispose();
    _passController.dispose();
    super.dispose();
  }

  void _onLoginPressed() async {
    // Llamamos a nuestro controlador de Riverpod
    await ref.read(loginControllerProvider.notifier).login(
          _emailController.text,
          _passController.text,
        );
    
    // Si no hubo error, podríamos navegar (lo configuraremos luego)
    // De momento veremos si el estado cambia.
  }

  @override
  Widget build(BuildContext context) {
    // Escuchamos el estado actual (¿Cargando? ¿Error?)
    final loginState = ref.watch(loginControllerProvider);

    // Escuchar cambios para mostrar errores (Snackbar) o navegar
    ref.listen<AsyncValue<void>>(loginControllerProvider, (previous, next) {
      if (next.hasError) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(next.error.toString()), backgroundColor: Colors.red),
        );
      }
      if (!next.isLoading && !next.hasError && next.hasValue) {
        // 1. Mensaje opcional
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text("¡Bienvenido a SavePoint!"), backgroundColor: Colors.green),
        );
        
        // 2. ¡NAVEGAR! (Esto es lo nuevo)
        context.go('/dashboard');
      }
    });

    return Scaffold(
      body: Center(
        child: Container(
          constraints: const BoxConstraints(maxWidth: 400), // Para que en web no se vea gigante
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.gamepad, size: 80, color: Color(0xFF6200EE)),
              const SizedBox(height: 20),
              Text("SavePoint", style: Theme.of(context).textTheme.headlineMedium),
              const SizedBox(height: 40),
              
              // Campo Email
              TextField(
                controller: _emailController,
                decoration: const InputDecoration(
                  labelText: "Email",
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.email),
                ),
              ),
              const SizedBox(height: 16),
              
              // Campo Contraseña
              TextField(
                controller: _passController,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: "Contraseña",
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.lock),
                ),
              ),
              const SizedBox(height: 24),

              // Botón de Entrar
              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton(
                  onPressed: loginState.isLoading ? null : _onLoginPressed,
                  child: loginState.isLoading
                      ? const CircularProgressIndicator(color: Colors.white)
                      : const Text("ENTRAR"),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}