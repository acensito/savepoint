import 'package:pocketbase/pocketbase.dart';
import 'package:savepoint/features/games/domain/entities/game.dart';
import 'package:savepoint/features/games/domain/models/game_model.dart';
import 'package:savepoint/features/games/domain/repositories/game_repository.dart';


class GameRepositoryImpl implements GameRepository {
  final PocketBase _pb;

  GameRepositoryImpl(this._pb);

  @override
  Future<List<Game>> getGames() async {
    try {
      // Pedimos la lista completa a la colección 'games'
      final records = await _pb.collection('games').getFullList(
        sort: '-created', // Los más nuevos primero
        expand: 'platform', // ¡IMPORTANTE! Pedimos los datos de la plataforma
      );

      // Convertimos cada registro JSON en un objeto GameModel
      return records.map((record) => GameModel.fromRecord(record)).toList();
      
    } catch (e) {
      // Si falla (ej. sin internet), devolvemos lista vacía o lanzamos error
      throw Exception("Error al cargar juegos: $e");
    }
  }
}