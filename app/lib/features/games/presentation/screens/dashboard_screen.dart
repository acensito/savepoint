
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:savepoint/features/auth/providers/auth_provider.dart';
import 'package:savepoint/features/games/presentation/screens/my_games_view.dart';

class DashboardScreen extends ConsumerStatefulWidget {
  const DashboardScreen({super.key});

  @override
  ConsumerState<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends ConsumerState<DashboardScreen> {
  int _selectedIndex = 0;

  void _onDestinationSelected(int index) {
    setState(() {
      _selectedIndex = index;
    });
  }

  void _logout() {
    ref.read(loginControllerProvider.notifier).logout();
    context.go('/'); // Volver al Login
  }

  @override
  Widget build(BuildContext context) {
    // 1. Definimos las vistas (Pantallas)
    final List<Widget> pages = [
      const Center(child: Text("📊 Resumen (Dashboard)")),
      const MyGamesView(),
      const Center(child: Text("⚙️ Configuración")),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        // --- VERSIÓN WEB / TABLET (Ancho > 800px) ---
        if (constraints.maxWidth > 800) {
          return Scaffold(
            body: Row(
              children: [
                // A. BARRA LATERAL (Sidebar)
                NavigationRail(
                  selectedIndex: _selectedIndex,
                  onDestinationSelected: _onDestinationSelected,
                  labelType: NavigationRailLabelType.all,
                  leading: const Padding(
                    padding: EdgeInsets.symmetric(vertical: 20),
                    child: Icon(Icons.gamepad, size: 40, color: Color(0xFF6200EE)),
                  ),
                  trailing: Expanded(
                    child: Align(
                      alignment: Alignment.bottomCenter,
                      child: Padding(
                        padding: const EdgeInsets.only(bottom: 20),
                        child: IconButton(
                          icon: const Icon(Icons.logout, color: Colors.red),
                          onPressed: _logout,
                          tooltip: "Cerrar Sesión",
                        ),
                      ),
                    ),
                  ),
                  destinations: const [
                    NavigationRailDestination(
                      icon: Icon(Icons.dashboard_outlined),
                      selectedIcon: Icon(Icons.dashboard),
                      label: Text('Inicio'),
                    ),
                    NavigationRailDestination(
                      icon: Icon(Icons.sports_esports_outlined),
                      selectedIcon: Icon(Icons.sports_esports),
                      label: Text('Colección'),
                    ),
                    NavigationRailDestination(
                      icon: Icon(Icons.settings_outlined),
                      selectedIcon: Icon(Icons.settings),
                      label: Text('Ajustes'),
                    ),
                  ],
                ),
                const VerticalDivider(thickness: 1, width: 1),
                
                // B. CONTENIDO PRINCIPAL
                Expanded(
                  child: pages[_selectedIndex],
                ),
              ],
            ),
          );
        }

        // --- VERSIÓN MÓVIL (Ancho < 800px) ---
        return Scaffold(
          appBar: AppBar(
            title: const Text("SavePoint"),
            actions: [
              IconButton(icon: const Icon(Icons.logout), onPressed: _logout),
            ],
          ),
          body: pages[_selectedIndex],
          bottomNavigationBar: NavigationBar(
            selectedIndex: _selectedIndex,
            onDestinationSelected: _onDestinationSelected,
            destinations: const [
              NavigationDestination(icon: Icon(Icons.dashboard), label: 'Inicio'),
              NavigationDestination(icon: Icon(Icons.sports_esports), label: 'Juegos'),
              NavigationDestination(icon: Icon(Icons.settings), label: 'Ajustes'),
            ],
          ),
        );
      },
    );
  }
}