<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2015-2019 Carlos Garcia Gomez <neorazorx@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Description of ie_csv_home
 *
 * @author Carlos García Gómez
 */
class ie_csv_factusol extends fs_controller
{

    /**
     *
     * @var string
     */
    public $contiene;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Importador FactuSol', 'admin');
    }

    protected function private_core()
    {
        $this->contiene = isset($_REQUEST['contiene']) ? $_REQUEST['contiene'] : '';

        switch ($this->contiene) {
            case 'articulos':
                $this->importar_articulos();
                break;

            case 'clientes':
                $this->importar_clientes();
                break;

            case 'familias':
                $this->importar_familias();
                break;

            case 'proveedores':
                $this->importar_proveedores();
                break;
        }
    }

    private function importar_articulos()
    {
        $total = 0;
        $articuloModel = new articulo();
        $impuestoModel = new impuesto();
        $impuestos = $impuestoModel->all();

        $csv = new ParseCsv\Csv();
        $csv->parse($_FILES['fcsv']['tmp_name']);

        foreach ($csv->data as $linea) {
            if (empty($linea) || count($linea) < 2 || !isset($linea['Código']) || empty($linea['Código'])) {
                continue;
            }

            $ref = empty($linea['Referencia']) ? $linea['Código'] : $linea['Referencia'];
            $articulo = $articuloModel->get($ref);
            if (empty($articulo)) {
                $articulo = new articulo();
            } elseif (!isset($_POST['sobreescribir'])) {
                continue;
            }

            $articulo->referencia = $ref;
            $articulo->descripcion = $linea['Descripción'];
            $articulo->costemedio = $articulo->preciocoste = (float) $linea['Costo'];
            $articulo->pvp = (float) $linea['Venta'];
            foreach ($impuestos as $imp) {
                if ($imp->is_default()) {
                    $articulo->codimpuesto = $imp->codimpuesto;
                }
            }

            if ($articulo->save()) {
                $articulo->sum_stock($this->empresa->codalmacen, (int) $linea['Stock()']);
                $total++;
            }
        }

        $this->new_message($total . ' artículos importados.');
    }

    private function importar_clientes()
    {
        $total = 0;
        $clienteModel = new cliente();

        $csv = new ParseCsv\Csv();
        $csv->parse($_FILES['fcsv']['tmp_name']);

        foreach ($csv->data as $linea) {
            $codeKey = isset($linea['Código']) ? 'Código' : 'Cód';
            if (empty($linea) || count($linea) < 2 || !isset($linea[$codeKey]) || empty($linea[$codeKey])) {
                continue;
            }

            $cliente = $clienteModel->get($linea[$codeKey]);
            if (empty($cliente)) {
                $cliente = new cliente();
            } elseif (!isset($_POST['sobreescribir'])) {
                continue;
            }

            $cliente->codcliente = $linea[$codeKey];
            $cliente->nombre = isset($linea['Nombre comercial']) ? $linea['Nombre comercial'] : $linea['Nombre'];
            $cliente->razonsocial = isset($linea['Nombre fiscal']) ? $linea['Nombre fiscal'] : $cliente->nombre;
            $cliente->telefono1 = $linea['Teléfono'];
            $cliente->cifnif = $linea['N.I.F.'];

            if (isset($linea['E-mail']) && !empty($linea['E-mail'])) {
                $cliente->email = $linea['E-mail'];
            }

            if (isset($linea['Móvil']) && !empty($linea['Móvil'])) {
                $cliente->telefono2 = $linea['Móvil'];
            }

            if ($cliente->save()) {
                $total++;

                /// guardamos la dirección
                $dir = new direccion_cliente();
                $dir->codcliente = $cliente->codcliente;
                $dir->codpais = $this->empresa->codpais;
                $dir->ciudad = $linea['Población'];
                $dir->codpostal = isset($linea['Cód. Postal']) ? $linea['Cód. Postal'] : $linea['C.P.'];
                $dir->direccion = isset($linea['Domicilio']) ? $linea['Domicilio'] : $linea['Dirección'];
                $dir->provincia = $linea['Provincia'];
                $dir->save();
            }
        }

        $this->new_message($total . ' clientes importados.');
    }

    private function importar_familias()
    {
        $total = 0;
        $familiaModel = new articulo();

        $csv = new ParseCsv\Csv();
        $csv->parse($_FILES['fcsv']['tmp_name']);

        foreach ($csv->data as $linea) {
            if (empty($linea) || count($linea) < 2 || !isset($linea['Código']) || empty($linea['Código'])) {
                continue;
            }

            $familia = $familiaModel->get($linea['Código']);
            if (empty($familia)) {
                $familia = new familia();
            } elseif (!isset($_POST['sobreescribir'])) {
                continue;
            }

            $familia->codfamilia = $linea['Código'];
            $familia->descripcion = $linea['Descripción'];
            if ($familia->save()) {
                $total++;
            }
        }

        $this->new_message($total . ' familias importadas.');
    }

    private function importar_proveedores()
    {
        $total = 0;
        $proveedorModel = new proveedor();

        $csv = new ParseCsv\Csv();
        $csv->parse($_FILES['fcsv']['tmp_name']);

        foreach ($csv->data as $linea) {
            $codeKey = isset($linea['Código']) ? 'Código' : 'Cód';
            if (empty($linea) || count($linea) < 2 || !isset($linea[$codeKey]) || empty($linea[$codeKey])) {
                continue;
            }

            $proveedor = $proveedorModel->get($linea[$codeKey]);
            if (empty($proveedor)) {
                $proveedor = new proveedor();
            } elseif (!isset($_POST['sobreescribir'])) {
                continue;
            }

            $proveedor->codproveedor = $linea[$codeKey];
            $proveedor->nombre = isset($linea['Nombre comercial']) ? $linea['Nombre comercial'] : $linea['Nombre'];
            $proveedor->razonsocial = isset($linea['Nombre fiscal']) ? $linea['Nombre fiscal'] : $proveedor->nombre;
            $proveedor->telefono1 = $linea['Teléfono'];
            $proveedor->cifnif = $linea['N.I.F.'];

            if (isset($linea['E-mail']) && !empty($linea['E-mail'])) {
                $proveedor->email = $linea['E-mail'];
            }

            if (isset($linea['Móvil']) && !empty($linea['Móvil'])) {
                $proveedor->telefono2 = $linea['Móvil'];
            }

            if ($proveedor->save()) {
                $total++;

                /// guardamos la dirección
                $dir = new direccion_proveedor();
                $dir->codproveedor = $proveedor->codproveedor;
                $dir->codpais = $this->empresa->codpais;
                $dir->ciudad = $linea['Población'];
                $dir->codpostal = isset($linea['Cód. Postal']) ? $linea['Cód. Postal'] : $linea['C.P.'];
                $dir->direccion = isset($linea['Domicilio']) ? $linea['Domicilio'] : $linea['Dirección'];
                $dir->provincia = $linea['Provincia'];
                $dir->save();
            }
        }

        $this->new_message($total . ' proveedors importados.');
    }
}
