import 'package:flutter/material.dart';
import '../../domain/entities/game.dart';

class GameCard extends StatelessWidget {
  final Game game;

  const GameCard({super.key, required this.game});

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 2, // Un poco de sombra para que destaque
      clipBehavior: Clip.antiAlias,
      child: Row( // <-- CAMBIO CLAVE: Usamos Row en vez de Column
        children: [
          // 1. LA IMAGEN (Izquierda)
          // Le damos un ancho fijo (ej. 100px) para que sea uniforme
          SizedBox(
            width: 100, 
            height: double.infinity, // Que ocupe toda la altura de la tarjeta
            child: game.coverUrl.isNotEmpty
                ? Image.network(
                    game.coverUrl,
                    fit: BoxFit.cover,
                    errorBuilder: (context, error, stackTrace) =>
                        const Center(child: Icon(Icons.broken_image, color: Colors.grey)),
                  )
                : Container(
                    color: Colors.grey.shade200,
                    child: const Icon(Icons.videogame_asset, color: Colors.grey),
                  ),
          ),

          // 2. LA INFORMACIÓN (Derecha)
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(12.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center, // Centrado verticalmente
                children: [
                  // Título
                  Text(
                    game.title,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 8),
                  
                  // Fila con Plataforma y Estado
                  Row(
                    children: [
                      // Badge de Plataforma
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(
                          color: Theme.of(context).colorScheme.primaryContainer,
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Text(
                          game.platformName,
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                            color: Theme.of(context).colorScheme.onPrimaryContainer,
                          ),
                        ),
                      ),
                      const Spacer(),
                      // Icono de estado (Ejemplo visual)
                      if (game.status == 'owned') 
                        const Icon(Icons.check_circle, size: 18, color: Colors.green),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}