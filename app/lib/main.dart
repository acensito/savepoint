import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:savepoint/core/router/app_router.dart';
import 'package:savepoint/core/theme/app_theme.dart';


void main() {
  runApp(
    // ProviderScope es obligatorio para que Riverpod funcione
    const ProviderScope(
      child: SavePointApp(),
    ),
  );
}

class SavePointApp extends StatelessWidget {
  const SavePointApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'SavePoint',
      debugShowCheckedModeBanner: false,
      
      // Conectamos el Tema
      theme: AppTheme.lightTheme,
      
      // Conectamos el Router
      routerConfig: appRouter,
    );
  }
}