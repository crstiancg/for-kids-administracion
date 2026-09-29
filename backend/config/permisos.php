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
        'categorias.index' => ['categorias.store', 'categorias.update', 'productos.store', 'productos.update', 'productos.index'],
        'tallas.show' => ['tallas.update'],
        'productos.show' => ['productos.update'],
        // El form de productos elige talla y color de cada variante.
        'tallas.index' => ['productos.store', 'productos.update'],
        'colores.index' => ['productos.store', 'productos.update'],
        // Los formularios de inventario buscan la variante de cada línea.
        // Los formularios de pedidos buscan variantes y clientes.
        'inventario.variantes' => ['inventario.entradas', 'inventario.salidas', 'inventario.ajustes', 'pedidos.store', 'pedidos.update'],
        'clientes.index' => ['pedidos.store', 'pedidos.update'],
        'clientes.show' => ['clientes.update'],
        // Autocompletar con RENIEC/SUNAT es parte de cargar un cliente.
        'clientes.consultar-documento' => ['clientes.store', 'clientes.update'],
        // A diferencia de los catálogos, quien ve la lista de pedidos puede
        // abrir su detalle (un vendedor necesita ver qué lleva cada pedido).
        'pedidos.show' => ['pedidos.index', 'pedidos.update'],
        // Cobrar, devolver o mover caja necesita saber si hay una abierta.
        'cajas.actual' => ['pedidos.pagos', 'pedidos.devoluciones', 'cajas.abrir', 'cajas.cerrar', 'cajas.movimientos', 'cajas.index'],
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
        'inventario' => 'Inventario',
        'clientes' => 'Clientes',
        'pedidos' => 'Pedidos',
        'cajas' => 'Caja',
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
    ],

];
