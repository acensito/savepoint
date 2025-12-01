import 'package:go_router/go_router.dart';
import 'package:savepoint/features/auth/presentation/login_screen.dart';

final appRouter = GoRouter(
  initialLocation: '/',
  routes: [
    GoRoute(
      path: '/',
      builder: (context, state) => const LoginScreen(),
    ),
    // Aquí añadiremos más rutas en el futuro (Dashboard, Details...)
  ],
);