import 'package:go_router/go_router.dart';
import 'package:savepoint/features/auth/presentation/screens/login_screen.dart';
import 'package:savepoint/features/games/presentation/screens/dashboard_screen.dart';

final appRouter = GoRouter(
  initialLocation: '/',
  routes: [
    GoRoute(
      path: '/',
      builder: (context, state) => const LoginScreen(),
    ),
    GoRoute(
      path: '/dashboard',
      builder: (context, state) => const DashboardScreen(),
    ),
  ],
);