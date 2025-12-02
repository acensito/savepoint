class Game {
  final String id;
  final String title;
  final String platformName; // Ej: "Switch"
  final String coverUrl;     // URL de la carátula
  final String status;       // Ej: "owned", "wishlist"

  const Game({
    required this.id,
    required this.title,
    required this.platformName,
    required this.coverUrl,
    required this.status,
  });
}