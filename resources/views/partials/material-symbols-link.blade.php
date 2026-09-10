{{--
    Ya no se incluye en ningún sitio (issue #186, auditoría de rendimiento del
    2026-09-10): la fuente vive autoalojada en resources/fonts/material-symbols-outlined.woff2,
    referenciada por el @font-face de resources/css/app.css. Este fichero se
    conserva solo como referencia de cómo regenerar ese .woff2 si hace falta
    añadir un icono nuevo — lista = x-gicon de las vistas + Edition::FORMATS +
    ligaduras usadas en JS (app.js). Icono nuevo sin añadir aquí no se pinta.

    Para regenerar tras añadir un icono a la lista de abajo:
    1. Pegar esta URL en el navegador (o `curl` con un User-Agent de navegador
       moderno, si no devuelve WOFF2 sino TTF/EOT):
       https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=account_circle,add,add_circle,album,arrow_back,arrow_forward,badge,bar_chart,brightness_auto,calendar_month,card_giftcard,category,check_circle,checklist,chevron_left,chevron_right,close,cloud,dark_mode,delete,density_small,description,domain,download,edit,emoji_events,error,event,expand_more,factory,favorite,forum,grid_view,group,hourglass_top,info,interests,inventory_2,joystick,light_mode,local_shipping,logout,memory,menu,menu_book,paid,person,price_check,print,progress_activity,public,qr_code_scanner,radio,save,schedule,sd_card,search,sell,settings,shopping_cart,sports_esports,star,star_rate,sticky_note_2,storefront,tag,travel_explore,tune,upload,upload_file,usb,view_list,wallpaper,warning&display=block
    2. La respuesta trae un único @font-face con una URL a fonts.gstatic.com
       — descargar ese fichero y sobrescribir
       resources/fonts/material-symbols-outlined.woff2 con él.
    3. `npm run build` para recompilarlo dentro de public/build.
--}}
