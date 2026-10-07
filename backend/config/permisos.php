<?php

/*
|--------------------------------------------------------------------------
| Autorización por nombre de ruta
|--------------------------------------------------------------------------
|
| El permiso ES el nombre de la ruta (roles.index, usuarios.store...). El
| middleware `autorizar.ruta` rechaza todo lo que no esté permitido, así que
| una ruta nueva nace bloqueada. Los permisos se crean con
| `php artisan permisos:sync` a partir de las rutas; no se tipean a mano.
|
*/

return [

    // Rutas que sólo exigen sesión (están en el grupo pero no piden permiso).
    'libres' => [
        'auth.user',
        'auth.logout',
        // Cada bloque del tablero se filtra por el permiso de su dato.
        'dashboard',
    ],

    // Permisos que también habilitan otra ruta: "esta ruta se permite a
    // quien tenga CUALQUIERA de estos". Sirve para las pantallas que leen
    // catálogos de otro módulo sin dar acceso a administrarlo.
    'implicitos' => [
        // El form de usuarios lista roles y permisos para tildar.
        'roles.index' => ['usuarios.store', 'usuarios.update'],
        // El form de roles y el de usuarios listan permisos para tildar.
        'permisos.index' => ['roles.store', 'roles.update', 'usuarios.store', 'usuarios.update'],
        // El diálogo "Nuevo permiso" lista las rutas que se pueden elegir.
        'permisos.rutas-disponibles' => ['permisos.store'],
        // Editar implica poder leer el registro que se edita.
        'roles.show' => ['roles.update'],
        'permisos.show' => ['permisos.update'],
        'usuarios.show' => ['usuarios.update'],
        'colores.show' => ['colores.update'],
        'categorias.show' => ['categorias.update'],
        // El form de categorías lista las demás para elegir la categoría padre.
        'categorias.index' => ['categorias.store', 'categorias.update', 'productos.store', 'productos.update', 'productos.index', 'ventas.store', 'ofertas.store', 'ofertas.update'],
        'tallas.show' => ['tallas.update'],
        'productos.show' => ['productos.update'],
        'ofertas.show' => ['ofertas.update'],
        // El form de ofertas busca el producto al que se aplica.
        'productos.index' => ['ofertas.store', 'ofertas.update'],
        // El form de productos elige talla y color de cada variante.
        // El form de productos y los filtros del punto de venta.
        'tallas.index' => ['productos.store', 'productos.update', 'ventas.store'],
        'colores.index' => ['productos.store', 'productos.update', 'ventas.store'],
        'ventas.catalogo' => ['ventas.store'],
        // Los formularios de inventario buscan la variante de cada línea.
        // Los formularios de pedidos buscan variantes y clientes.
        'inventario.variantes' => ['inventario.entradas', 'inventario.salidas', 'inventario.ajustes', 'pedidos.store', 'pedidos.update', 'ventas.store'],
        'clientes.index' => ['pedidos.store', 'pedidos.update', 'ventas.store'],
        'clientes.show' => ['clientes.update'],
        // Autocompletar con RENIEC/SUNAT es parte de cargar un cliente.
        'clientes.consultar-documento' => ['clientes.store', 'clientes.update'],
        // A diferencia de los catálogos, quien ve la lista de pedidos puede
        // abrir su detalle (un vendedor necesita ver qué lleva cada pedido),
        // y el punto de venta lo lee para reimprimir el ticket.
        'pedidos.show' => ['pedidos.index', 'pedidos.update', 'ventas.store'],
        // Cobrar, devolver, mover caja o vender necesita saber si hay una abierta.
        'cajas.actual' => ['pedidos.pagos', 'pedidos.devoluciones', 'cajas.abrir', 'cajas.cerrar', 'cajas.movimientos', 'cajas.index', 'ventas.store'],
        'cajas.show' => ['cajas.index', 'cajas.cerrar'],
    ],

    // Para la descripción que genera permisos:sync: "Roles · Crear".
    'recursos' => [
        'roles' => 'Roles',
        'permisos' => 'Permisos',
        'usuarios' => 'Usuarios',
        'colores' => 'Colores',
        'categorias' => 'Categorías',
        'tallas' => 'Tallas',
        'productos' => 'Productos',
        'ofertas' => 'Ofertas',
        'inventario' => 'Inventario',
        'clientes' => 'Clientes',
        'pedidos' => 'Pedidos',
        'cajas' => 'Caja',
        'ventas' => 'Punto de venta',
        'etiquetas' => 'Etiquetas',
    ],

    'acciones' => [
        'index' => 'Ver listado',
        'show' => 'Ver detalle',
        'store' => 'Crear',
        'update' => 'Editar',
        'destroy' => 'Eliminar',
        'toggle-active' => 'Dar de baja / activar',
        'sesiones' => 'Ver sesiones',
        'sesiones.revocar' => 'Cerrar sesiones',
        'rutas-disponibles' => 'Ver rutas sin permiso',
        'entradas' => 'Registrar entradas',
        'salidas' => 'Registrar salidas',
        'ajustes' => 'Ajustar por conteo',
        'variantes' => 'Buscar variantes',
        'consultar-documento' => 'Consultar DNI/RUC',
        'confirmar' => 'Confirmar (descuenta stock)',
        'entregar' => 'Marcar entregado',
        'cancelar' => 'Cancelar',
        'pagos' => 'Cobrar',
        'devoluciones' => 'Devolver pagos',
        'actual' => 'Ver caja abierta',
        'abrir' => 'Abrir caja',
        'cerrar' => 'Cerrar caja (arqueo)',
        'movimientos' => 'Registrar ingresos y egresos',
        'catalogo' => 'Ver catálogo',
        'imprimir' => 'Imprimir códigos de barras',
    ],

];
