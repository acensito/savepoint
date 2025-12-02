import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:savepoint/core/providers/core_providers.dart';
import 'package:savepoint/features/games/domain/entities/game.dart';
import 'package:savepoint/features/games/domain/repositories/game_repository.dart';
import 'package:savepoint/features/games/domain/repositories/game_repository_impl.dart';


// 1. Provider del Repositorio (Inyección de Dependencias)
final gameRepositoryProvider = Provider<GameRepository>((ref) {
  final pb = ref.watch(pocketBaseProvider);
  return GameRepositoryImpl(pb);
});

// 2. Provider de la Lista (Estado Asíncrono)
// Este es el que usará la pantalla para dibujar
final gamesListProvider = FutureProvider.autoDispose<List<Game>>((ref) async {
  final repository = ref.watch(gameRepositoryProvider);
  return repository.getGames();
});