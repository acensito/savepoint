import 'package:pocketbase/pocketbase.dart';
import '../../domain/entities/game.dart';
import '../../../../core/constants/app_constants.dart';

class GameModel extends Game {
  const GameModel({
    required super.id,
    required super.title,
    required super.platformName,
    required super.coverUrl,
    required super.status,
  });

  // Factory: Convierte un registro de PocketBase en un GameModel
  factory GameModel.fromRecord(RecordModel record) {
    
    // 1. Obtener la Plataforma (SINTAXIS MODERNA)
    // Usamos .get<List<RecordModel>> para acceder a la relación expandida.
    // El segundo parámetro [] es el valor por defecto si no encuentra nada.
    String platformName = "Desconocida";
    
    // Buscamos dentro de 'expand.platform'
    // Nota: Aunque sea una relación única, PocketBase devuelve una Lista en el expand
    final expandedList = record.get<List<RecordModel>>('expand.platform', []);

    // 2. Si la caja no está vacía, sacamos el primer elemento
    if (expandedList.isNotEmpty) {
      final platformData = expandedList.first;
      // Aquí ya tenemos el objeto completo de la plataforma
      platformName = platformData.get('name'); // O 'nombre' si lo tienes en español
    }

    // 2. Construimos la URL de la imagen
    String cover = "";
    // Usamos .getString para el nombre del archivo, es más seguro que data['cover']
    final coverFilename = record.get('cover');
    
    if (coverFilename.isNotEmpty) {
      cover = "${AppConstants.baseUrl}/api/files/${record.collectionId}/${record.id}/$coverFilename";
    }

    return GameModel(
      id: record.id,
      title: record.get('title', 'Sin Título'),
      platformName: platformName,// Valor por defecto si no está expandido
      coverUrl: cover,
      status: record.get('status', 'owned'),
    );
  }
}