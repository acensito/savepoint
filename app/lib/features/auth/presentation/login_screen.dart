import 'package:flutter/material.dart';

class LoginScreen extends StatelessWidget {
  const LoginScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.gamepad, size: 80, color: Color(0xFF6200EE)),
            const SizedBox(height: 20),
            Text(
              "SavePoint",
              style: Theme.of(context).textTheme.headlineMedium,
            ),
            const SizedBox(height: 10),
            const Text("Estructura Clean Architecture lista"),
          ],
        ),
      ),
    );
  }
}