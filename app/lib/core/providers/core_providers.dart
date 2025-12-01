import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:pocketbase/pocketbase.dart';
import '../constants/app_constants.dart';

// Este es el objeto "sagrado" que conecta con el servidor.
// Lo creamos una vez y lo usamos en toda la app.
final pocketBaseProvider = Provider<PocketBase>((ref) {
  return PocketBase(AppConstants.baseUrl);
});