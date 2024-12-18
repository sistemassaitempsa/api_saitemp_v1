<?php
// Crear instancia de DOMDocument
$dom = new DOMDocument('1.0', 'utf-8');
$dom->formatOutput = true; // Formatear el XML

// Nodo raíz
$root = $dom->createElement('root');
$dom->appendChild($root);

// Nodo documento
$documento = $dom->createElement('documento');
$root->appendChild($documento);

// Nodo idnumeracion
$idnumeracion = $dom->createElement('idnumeracion', '12345');
$documento->appendChild($idnumeracion);

// Nodo numero
$numero = $dom->createElement('numero', '001');
$documento->appendChild($numero);

// Nodo idambiente
$idambiente = $dom->createElement('idambiente', '1');
$documento->appendChild($idambiente);

// Fecha documento
$fechaDocumento = $dom->createElement('fechadocumento', '2024-12-17 11:13:39');
$documento->appendChild($fechaDocumento);

// Fecha vencimiento
$fechaVencimiento = $dom->createElement('fechavencimiento', '2024-12-30 23:59:59');
$documento->appendChild($fechaVencimiento);

// Moneda
$moneda = $dom->createElement('moneda', 'COP');
$documento->appendChild($moneda);

// Totales
$totales = $dom->createElement('totales');
$documento->appendChild($totales);

$totalBruto = $dom->createElement('totalbruto', '12912.00');
$totales->appendChild($totalBruto);

$totalCargos = $dom->createElement('totalcargos', '2453.28');
$totales->appendChild($totalCargos);

$totalAPagar = $dom->createElement('totala_pagar', '15777.28');
$totales->appendChild($totalAPagar);

// Items
$items = $dom->createElement('items');
$documento->appendChild($items);

$item = $dom->createElement('item');
$items->appendChild($item);

$descripcion = $dom->createElement('descripcion', 'SERVICIOS PRESTADOS EN EL PERIODO');
$item->appendChild($descripcion);

$cantidad = $dom->createElement('cantidad', '1');
$item->appendChild($cantidad);

$precioUnitario = $dom->createElement('preciounitario', '12912.00');
$item->appendChild($precioUnitario);

$unidadMedida = $dom->createElement('unidaddemedida', 'EA');
$item->appendChild($unidadMedida);

// Guardar XML en archivo
$rutaArchivo = 'factura.xml';
$dom->save($rutaArchivo);

echo "XML generado correctamente en: $rutaArchivo";