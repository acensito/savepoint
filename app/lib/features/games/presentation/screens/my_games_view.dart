import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:savepoint/features/games/presentation/providers/game_repository_provider.dart';
import 'package:savepoint/features/games/presentation/widgets/game_card.dart';

class MyGamesView extends ConsumerWidget {
  const MyGamesView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // 1. Escuchamos al provider (Cargando, Error o Datos)
    final gamesState = ref.watch(gamesListProvider);

    return Scaffold(
      // 2. Botón flotante para añadir (lo conectaremos en el futuro)
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () {
            // TODO: Ir a pantalla de añadir juego
        },
        icon: const Icon(Icons.add),
        label: const Text("Añadir Juego"),
      ),
      
      // 3. El cuerpo de la pantalla maneja los 3 estados
      body: gamesState.when(
        
        // A. CARGANDO
        loading: () => const Center(child: CircularProgressIndicator()),
        
        // B. ERROR
        error: (error, stack) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 60, color: Colors.red),
              const SizedBox(height: 10),
              Text("Error: ${error.toString()}"),
              const SizedBox(height: 10),
              // Botón para reintentar
              ElevatedButton(
                onPressed: () => ref.refresh(gamesListProvider),
                child: const Text("Reintentar"),
              )
            ],
          ),
        ),
        
        // C. DATOS CARGADOS (La lista de juegos)
        data: (games) {
          if (games.isEmpty) {
            return const Center(
              child: Text("No tienes juegos aún. ¡Añade el primero!"),
            );
          }

          // Grid Inteligente (Responsive)
        return GridView.builder(
            padding: const EdgeInsets.all(16),
            gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
              // TRUCO RESPONSIVE:
              // 1. Ponemos un ancho máximo grande (500). 
              //    - En móvil (ancho ~380) solo cabrá 1 -> Lista vertical.
              //    - En web (ancho ~1920) cabrán 3 o 4 -> Grid.
              maxCrossAxisExtent: 500, 
              
              // 2. Relación de aspecto: Ancho / Alto
              //    - 2.8 significa que es casi 3 veces más ancha que alta.
              //    - Ajusta este número si ves la tarjeta muy alta o muy baja.
              childAspectRatio: 2.8, 
              
              crossAxisSpacing: 16,
              mainAxisSpacing: 16,
            ),
            itemCount: games.length,
            itemBuilder: (context, index) {
              return GameCard(game: games[index]);
            },
          );
        },
      ),
    );
  }
}