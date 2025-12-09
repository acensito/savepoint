
import 'package:savepoint/features/games/domain/entities/game.dart';

abstract class GameRepository {
  // Solo queremos obtener la lista de juegos por ahora
  Future<List<Game>> getGames();

}