<?php

/*
 * This file is part of FacturaScripts
 * Copyright (C) 2015-2017  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('almacen.php');
require_model('articulo.php');
require_model('articulo_proveedor.php');
require_model('cliente.php');
require_model('cuenta_banco_cliente.php');
require_model('cuenta_banco_proveedor.php');
require_model('fabricantes.php');
require_model('familia.php');
require_model('grupo_clientes.php');
require_model('impuesto.php');
require_model('pedido_cliente.php');
require_model('proveedor.php');
require_model('serie.php');

/**
 * Description of ie_csv_home
 *
 * @author Carlos García Gómez
 */
class ie_csv_factusol extends fs_controller
{

   public $almacen;
   public $contiene;
   public $familia;
   public $impuesto;
   public $separador;
   public $serie;

   public function __construct()
   {
      parent::__construct(__CLASS__, 'Importador FactuSol', 'admin');
   }

   protected function private_core()
   {
      $this->contiene = 'articulos';
      if(isset($_REQUEST['contiene']))
      {
         $this->contiene = $_REQUEST['contiene'];
      }

      $this->almacen = new almacen();
      $this->familia = new familia();
      $this->impuesto = new impuesto();
      $this->separador = ';';
      $this->serie = new serie();

      if(isset($_POST['contiene']))
      {
         $this->separador = $_POST['separador'];

         if(is_uploaded_file($_FILES['fcsv']['tmp_name']))
         {
            if($_POST['contiene'] == 'clientes')
            {
               $this->importar_clientes();
            }
            else if($_POST['contiene'] == 'proveedores')
            {
               $this->importar_proveedores();
            }
            else if($_POST['contiene'] == 'articulos')
            {
               $this->importar_articulos();
            }
            else if($_POST['contiene'] == 'familias')
            {
               $this->importar_familias();
            }
            else
            {
               $this->new_error_msg('Opción de importación desconocida.');
            }
         }
         else
            $this->new_error_msg('No has seleccionado ningún archivo.');
      }
   }

   private function importar_clientes()
   {
      $old_method = FALSE;
      $plinea = FALSE;
      $total = 0;

      $fcsv = fopen($_FILES['fcsv']['tmp_name'], 'r');
      if($fcsv)
      {
         while(!feof($fcsv)) {
            $aux = trim(fgets($fcsv));
            if($aux != '')
            {
               $linea = $this->custom_explode($_POST['separador'], $aux);
               if(in_array('Código:', $linea))
               {
                  if($this->importar_cliente_aux($fcsv, $linea, $total))
                  {
                     $total++;
                  }
                  else
                     break;
               }
               else if(in_array('Cód.', $linea) OR $old_method)
               {
                  $old_method = TRUE;
                  if($this->importar_cliente_old($plinea, $aux))
                  {
                     $total++;
                  }
                  else
                     break;
               }
               else
               {
                  $this->new_error_msg("En la línea 1 del registro " . ($total + 1) . " falta el campo 'Código:' o 'Cód.'.");
                  break;
               }
            }
         }

         $this->new_message($total . ' clientes importados.');
      }
   }

   private function importar_cliente_aux(&$fcsv, &$linea, &$total)
   {
      $error_reg = FALSE;

      $sql = "SELECT * FROM clientes WHERE codcliente = " . $this->empresa->var2str($linea[1]) . ";";
      $data = $this->db->select($sql);
      if($data)
      {
         if(isset($_POST['sobreescribir']))
         {
            $cliente = new cliente($data[0]);
         }
      }
      else
      {
         $cliente = new cliente();
         $cliente->codcliente = $linea[1];
      }

      //Linea 2
      $aux = trim(fgets($fcsv));
      if(!$error_reg)
      {
         if($aux != '')
         {
            $linea = $this->custom_explode($_POST['separador'], $aux);
            if(!in_array('N.I.F.:', $linea))
            {
               $this->new_error_msg("En la línea 2 del registro " . ($total + 1) . " falta el campo \'N.I.F.:\'");
               $error_reg = true;
            }

            if(!in_array('Forma de pago:', $linea))
            {
               $this->new_error_msg("En la línea 2 del registro " . ($total + 1) . " falta el campo \'N.I.F.:\'");
               $error_reg = true;
            }

            if(!$error_reg)
            {
               $cliente->cifnif = strtoupper(str_replace('-', '', $linea[1]));
               $cliente->codpago = $linea[3];
            }
         }
         else
         {
            $this->new_error_msg("En la línea 2 del registro " . ($total + 1) . " esta vacío");
            $error_reg = true;
         }
      }

      //Linea 3
      $aux = trim(fgets($fcsv));
      if(!$error_reg)
      {
         if($aux != '')
         {
            $linea = $this->custom_explode($_POST['separador'], $aux);
            if(!in_array('Nombre fiscal:', $linea))
            {
               $this->new_error_msg("En la línea 3 del registro " . ($total + 1) . " falta el campo \'Nombre fiscal:\'");
               $error_reg = true;
            }

            if(!in_array('% Financiación:', $linea))
            {
               $this->new_error_msg("En la línea 3 del registro " . ($total + 1) . " falta el campo \'% Financiación:\'");
               $error_reg = true;
            }

            if(!$error_reg)
            {
               $cliente->razonsocial = $linea[1];
            }
         }
         else
         {
            $this->new_error_msg("En la línea 3 del registro " . ($total + 1) . " esta vacío");
            $error_reg = true;
         }
      }

      //Línea 4
      $aux = trim(fgets($fcsv));
      if(!$error_reg)
      {
         if($aux != '')
         {
            $linea = $this->custom_explode($_POST['separador'], $aux);
            if(!in_array('Nombre comercial:', $linea))
            {
               $this->new_error_msg("En la línea 4 del registro " . ($total + 1) . " falta el campo \'Nombre comercial:\'");
               $error_reg = true;
            }

            if(!in_array('Días de pago:', $linea))
            {
               $this->new_error_msg("En la línea 4 del registro " . ($total + 1) . " falta el campo \'Días de pago:\'");
               $error_reg = true;
            }

            if(!$error_reg)
            {
               $cliente->nombre = $linea[1];
            }
         }
         else
         {
            $this->new_error_msg("En la línea 4 del registro " . ($total + 1) . " esta vacío");
            $error_reg = true;
         }
      }

      //Línea 5
      $aux = trim(fgets($fcsv));
      if(!$error_reg)
      {
         if($aux != '')
         {
            $linea = $this->custom_explode($_POST['separador'], $aux);
            if(!in_array('Domicilio:', $linea))
            {
               $this->new_error_msg("En la línea 5 del registro " . ($total + 1) . " falta el campo \'Domicilio:\'");
               $error_reg = true;
            }

            if(!in_array('Tarifa:', $linea))
            {
               $this->new_error_msg("En la línea 5 del registro " . ($total + 1) . " falta el campo \'Tarifa:\'");
               $error_reg = true;
            }

            if(!$error_reg AND $linea[1] != '')
            {
               $direccion = new direccion_cliente();
               $direccion->codcliente = $cliente->codcliente;
               $direccion->descripcion = 'General';

               if($cliente->exists())
               {
                  foreach($cliente->get_direcciones() as $dir)
                  {
                     $direccion = $dir;
                     break;
                  }
               }

               $direccion->codpais = $this->empresa->codpais;
               $direccion->direccion = $linea[1];
            }
         }
         else
         {
            $this->new_error_msg("En la línea 5 del registro " . ($total + 1) . " esta vacío");
            $error_reg = true;
         }
      }

      //Línea 6
      //C.P.;;Tipo de cliente:;1 
      $aux = trim(fgets($fcsv));
      if(!$error_reg)
      {
         if($aux != '')
         {
            $linea = $this->custom_explode($_POST['separador'], $aux);
            if(!in_array('C.P.', $linea))
            {
               $this->new_error_msg("En la línea 6 del registro " . ($total + 1) . " falta el campo \'C.P.\'");
               $error_reg = true;
            }

            if(!in_array('Tipo de cliente:', $linea))
            {
               $this->new_error_msg("En la línea 6 del registro " . ($total + 1) . " falta el campo \'Tipo de cliente:\'");
               $error_reg = true;
            }

            if(!$error_reg AND isset($direccion))
            {
               $direccion->codpostal = $linea[1];
            }
         }
         else
         {
            $this->new_error_msg("En la línea 6 del registro " . ($total + 1) . " esta vacío");
            $error_reg = true;
         }
      }

      //Línea 7
      //Población:;;Tipo de documento:;0 
      $aux = trim(fgets($fcsv));
      if(!$error_reg)
      {
         if($aux != '')
         {
            $linea = $this->custom_explode($_POST['separador'], $aux);
            if(!in_array('Población:', $linea))
            {
               $this->new_error_msg("En la línea 7 del registro " . ($total + 1) . " falta el campo \'Población:\'");
               $error_reg = true;
            }

            if(!in_array('Tipo de documento:', $linea))
            {
               $this->new_error_msg("En la línea 7 del registro " . ($total + 1) . " falta el campo \'Tipo de documento:\'");
               $error_reg = true;
            }

            if(!$error_reg AND isset($direccion))
            {
               $direccion->ciudad = $linea[1];
            }
         }
         else
         {
            $this->new_error_msg("En la línea 7 del registro " . ($total + 1) . " esta vacío");
            $error_reg = true;
         }
      }

      //Línea 8
      //Provincia:;;Descuentos fijos:;0  0  0
      $aux = trim(fgets($fcsv));
      if(!$error_reg)
      {
         if($aux != '')
         {
            $linea = $this->custom_explode($_POST['separador'], $aux);
            if(!in_array('Provincia:', $linea))
            {
               $this->new_error_msg("En la línea 8 del registro " . ($total + 1) . " falta el campo \'Provincia:\'");
               $error_reg = true;
            }

            if(!in_array('Descuentos fijos:', $linea))
            {
               $this->new_error_msg("En la línea 8 del registro " . ($total + 1) . " falta el campo \'Descuentos fijos:\'");
               $error_reg = true;
            }

            if(!$error_reg AND isset($direccion))
            {
               $direccion->provincia = $linea[1];
            }
         }
         else
         {
            $this->new_error_msg("En la línea 8 del registro " . ($total + 1) . " esta vacío");
            $error_reg = true;
         }
      }

      //Línea 9
      //Teléfono:;655 071 047;Tarifa especial:;No
      $aux = trim(fgets($fcsv));
      if(!$error_reg)
      {
         if($aux != '')
         {
            $linea = $this->custom_explode($_POST['separador'], $aux);
            if(!in_array('Teléfono:', $linea))
            {
               $this->new_error_msg("En la línea 9 del registro " . ($total + 1) . " falta el campo \'Teléfono:\'");
               $error_reg = true;
            }

            if(!in_array('Tarifa especial:', $linea))
            {
               $this->new_error_msg("En la línea 9 del registro " . ($total + 1) . " falta el campo \'Tarifa especial:\'");
               $error_reg = true;
            }

            if(!$error_reg)
            {
               $telefonos = explode('/', str_replace('-', '/', $linea[1]));
               $cliente->telefono1 = $telefonos[0];
               if(isset($telefonos[1]))
               {
                  $cliente->telefono2 = $telefonos[1];
               }
            }
         }
         else
         {
            $this->new_error_msg("En la línea 9 del registro " . ($total + 1) . " esta vacío");
            $error_reg = true;
         }
      }

      //Línea 10
      //Fax:;;Actividad:;  
      $aux = trim(fgets($fcsv));
      if(!$error_reg)
      {
         if($aux != '')
         {
            $linea = $this->custom_explode($_POST['separador'], $aux);
            if(!in_array('Fax:', $linea))
            {
               $this->new_error_msg("En la línea 10 del registro " . ($total + 1) . " falta el campo \'Fax:\'");
               $error_reg = true;
            }

            if(!in_array('Actividad:', $linea))
            {
               $this->new_error_msg("En la línea 10 del registro " . ($total + 1) . " falta el campo \'Actividad:\'");
               $error_reg = true;
            }

            if(!$error_reg)
            {
               $cliente->fax = $linea[1];
            }
         }
         else
         {
            $this->new_error_msg("En la línea 10 del registro " . ($total + 1) . " esta vacío");
            $error_reg = true;
         }
      }

      //Línea 11
      //Móvil:;;Portes:;Debidos
      $aux = trim(fgets($fcsv));
      if(!$error_reg)
      {
         if($aux != '')
         {
            $linea = $this->custom_explode($_POST['separador'], $aux);
            if(!in_array('Móvil:', $linea))
            {
               $this->new_error_msg("En la línea 11 del registro " . ($total + 1) . " falta el campo \'Móvil:\'");
               $error_reg = true;
            }

            if(!in_array('Portes:', $linea))
            {
               $this->new_error_msg("En la línea 11 del registro " . ($total + 1) . " falta el campo \'Portes:\'");
               $error_reg = true;
            }

            if(!$error_reg)
            {
               if($linea[1])
               {
                  $cliente->telefono2 = $linea[1];
               }
            }
         }
         else
         {
            $this->new_error_msg("En la línea 11 del registro " . ($total + 1) . " esta vacío");
            $error_reg = true;
         }
      }

      //Línea 12
      //Persona de contacto:;;IVA:;Si
      $aux = trim(fgets($fcsv));

      //Línea 13
      //Agente:;0  -  ;Recargo:;No
      $aux = trim(fgets($fcsv));
      if(!$error_reg)
      {
         if($aux != '')
         {
            $linea = $this->custom_explode($_POST['separador'], $aux);
            if(!in_array('Recargo:', $linea))
            {
               $this->new_error_msg("En la línea 13 del registro " . ($total + 1) . " falta el campo \'Recargo:\'");
               $error_reg = true;
            }

            if(!$error_reg)
            {
               if($linea[3] === 'Si')
               {
                  $cliente->recargo = true;
               }
               else
               {
                  $cliente->recargo = false;
               }
            }
         }
         else
         {
            $this->new_error_msg("En la línea 13 del registro " . ($total + 1) . " esta vacío");
            $error_reg = true;
         }
      }

      //Línea 14
      //Fecha de alta:;19/05/2008;;
      $aux = trim(fgets($fcsv));
      if(!$error_reg)
      {
         if($aux != '')
         {
            $linea = $this->custom_explode($_POST['separador'], $aux);
            if(!in_array('Fecha de alta:', $linea))
            {
               $this->new_error_msg("En la línea 14 del registro " . ($total + 1) . " falta el campo \'Fecha de alta:\'");
               $error_reg = true;
            }

            if(!$error_reg)
            {
               $cliente->fechaalta = date('d-m-Y', strtotime($linea[1]));
            }
         }
         else
         {
            $this->new_error_msg("En la línea 14 del registro " . ($total + 1) . " esta vacío");
            $error_reg = true;
         }
      }

      //Línea 15
      //Horario:;;;
      $aux = trim(fgets($fcsv));

      //Línea 16
      //E-mail:;;;
      $aux = trim(fgets($fcsv));
      if(!$error_reg)
      {
         if($aux != '')
         {
            $linea = $this->custom_explode($_POST['separador'], $aux);
            if(!in_array('E-mail:', $linea))
            {
               $this->new_error_msg("En la línea 16 del registro " . ($total + 1) . " falta el campo \'E-mail:\'");
               $error_reg = true;
            }

            if(!$error_reg)
            {
               $cliente->email = $linea[1];
            }
         }
         else
         {
            $this->new_error_msg("En la línea 16 del registro " . ($total + 1) . " esta vacío");
            $error_reg = true;
         }
      }

      //Línea 17
      //Web:;;;
      $aux = trim(fgets($fcsv));
      if(!$error_reg)
      {
         if($aux != '')
         {
            $linea = $this->custom_explode($_POST['separador'], $aux);
            if(!in_array('Web:', $linea))
            {
               $this->new_error_msg("En la línea 17 del registro " . ($total + 1) . " falta el campo \'Web:\'");
               $error_reg = true;
            }

            if(!$error_reg)
            {
               $cliente->web = $linea[1];
            }
         }
         else
         {
            $this->new_error_msg("En la línea 17 del registro " . ($total + 1) . " esta vacío");
            $error_reg = true;
         }
      }

      //Línea 18
      //Observaciones:;;;
      $aux = trim(fgets($fcsv));
      if(!$error_reg)
      {
         if($aux != '')
         {
            $linea = $this->custom_explode($_POST['separador'], $aux);
            if(!in_array('Observaciones:', $linea))
            {
               $this->new_error_msg("En la línea 18 del registro " . ($total + 1) . " falta el campo \'Observaciones:\'");
               $error_reg = true;
            }

            if(!$error_reg)
            {
               $cliente->observaciones = $linea[1];
            }
         }
         else
         {
            $this->new_error_msg("En la línea 18 del registro " . ($total + 1) . " esta vacío");
            $error_reg = true;
         }
      }

      //Línea 19
      //Vacio
      $aux = trim(fgets($fcsv));
      if(!isset($cliente))
      {
         $this->new_error_msg('Error al procesar el cliente.');
         return FALSE;
      }
      else if($cliente->save())
      {
         if(isset($direccion))
         {
            $direccion->save();
            unset($direccion);
         }

         return TRUE;
      }
      else
      {
         $this->new_error_msg('Error al guardar los datos del cliente.');
         return FALSE;
      }
   }

   private function importar_cliente_old(&$plinea, &$aux)
   {
      $ok = TRUE;

      if($plinea)
      {
         $linea = array();
         foreach($this->custom_explode($_POST['separador'], $aux) as $i => $value)
         {
            if($i < count($plinea))
            {
               $linea[$plinea[$i]] = $value;
            }
         }

         //Cód.;Nombre;Dirección;C.P.;Población;Provincia;Teléfono;N.I.F.
         /// ¿Existe el cliente?
         $sql = "SELECT * FROM clientes";
         if($linea['Nombre'] != '')
         {
            $sql .= " WHERE nombre = " . $this->empresa->var2str($linea['Nombre']) . ";";
            $data = $this->db->select($sql);
            if(count($linea) == count($plinea) AND ( !$data OR isset($_POST['sobreescribir'])))
            {
               if($data)
               {
                  if(isset($_POST['sobreescribir']))
                  {
                     $cliente = new cliente($data[0]);
                  }
                  else
                     $ok = FALSE;
               }
               else
               {
                  $cliente = new cliente();
                  $cliente->codcliente = $linea['Cód.'];
               }

               $cliente->nombre = $cliente->razonsocial = $linea['Nombre'];
               $cliente->cifnif = strtoupper(str_replace('-', '', $linea['N.I.F.']));

               $telefonos = explode('/', str_replace('-', '/', $linea['Teléfono']));
               $cliente->telefono1 = $telefonos[0];
               if(isset($telefonos[1]))
               {
                  $cliente->telefono2 = $telefonos[1];
               }

               if($cliente->save())
               {
                  if($linea['Dirección'] != '')
                  {
                     $direccion = new direccion_cliente();
                     $direccion->codcliente = $cliente->codcliente;
                     $direccion->descripcion = 'General';

                     if($cliente->exists())
                     {
                        foreach($cliente->get_direcciones() as $dir)
                        {
                           $direccion = $dir;
                           break;
                        }
                     }

                     $direccion->codpais = $this->empresa->codpais;
                     $direccion->direccion = $linea['Dirección'];
                     $direccion->codpostal = $linea['C.P.'];
                     $direccion->ciudad = $linea['Población'];
                     $direccion->provincia = $linea['Provincia'];
                     $direccion->save();
                  }
               }
               else
               {
                  $this->new_error_msg('Error al guardar los datos del cliente.');
               }
            }
         }
      }
      else
      {
         $plinea = $this->custom_explode($_POST['separador'], $aux);

         $columnas = "Cód.;Nombre;Dirección;C.P.;Población;Provincia;Teléfono;N.I.F.";
         if(!$this->validar_columnas($plinea, $this->custom_explode(';', $columnas)))
         {
            $this->new_error_msg('El archivo no contiene las columnas necesarias.');
            $ok = FALSE;
         }
      }

      return $ok;
   }

   private function importar_proveedores()
   {
      $plinea = FALSE;
      $total = 0;
      $numlinea = 0;
      $ultimoregistro = false;

      $fcsv = fopen($_FILES['fcsv']['tmp_name'], 'r');
      if($fcsv)
      {
         while(!feof($fcsv)) {
            $aux = trim(fgets($fcsv));
            $numlinea++;
            if($aux != '')
            {
               if($ultimoregistro)
               {
                  //Si no es el ultimo registro nos salimos del bucle y mandamos un error
                  $this->new_error_msg('Se necesita un Nombre (Registro número ' . ($numlinea - 1) . ').');
                  break;
               }

               if($plinea)
               {
                  $linea = array();
                  foreach($this->custom_explode($_POST['separador'], $aux) as $i => $value)
                  {
                     if($i < count($plinea))
                     {
                        $linea[$plinea[$i]] = $value;
                     }
                  }

                  //Cód.;Nombre;Dirección;C.P.;Población;Provincia;Teléfono;N.I.F.
                  /// ¿Existe el proveedor?
                  $sql = "SELECT * FROM proveedores";
                  if($linea['Nombre'] != '')
                  {
                     $sql .= " WHERE nombre = ".$this->empresa->var2str($linea['Nombre']).";";
                  }
                  else
                  {
                     $ultimoregistro = true;
                     continue;
                  }

                  $data = $this->db->select($sql);
                  if(count($linea) == count($plinea) AND ( !$data OR isset($_POST['sobreescribir'])))
                  {
                     if($data)
                     {
                        if(isset($_POST['sobreescribir']))
                        {
                           $proveedor = new proveedor($data[0]);
                        }
                        else
                           break;
                     }
                     else
                     {
                        $proveedor = new proveedor();
                        $proveedor->codproveedor = $linea['Cód.'];
                     }

                     $proveedor->nombre = $proveedor->razonsocial = $linea['Nombre'];
                     $proveedor->cifnif = strtoupper(str_replace('-', '', $linea['N.I.F.']));

                     $telefonos = explode('/', str_replace('-', '/', $linea['Teléfono']));
                     $proveedor->telefono1 = $telefonos[0];
                     if(isset($telefonos[1]))
                     {
                        $proveedor->telefono2 = $telefonos[1];
                     }

                     if($proveedor->save())
                     {
                        $total++;

                        if($linea['Dirección'] != '')
                        {
                           $direccion = new direccion_proveedor();
                           $direccion->codproveedor = $proveedor->codproveedor;
                           $direccion->descripcion = 'General';

                           if($proveedor->exists())
                           {
                              foreach($proveedor->get_direcciones() as $dir)
                              {
                                 $direccion = $dir;
                                 break;
                              }
                           }

                           $direccion->codpais = $this->empresa->codpais;
                           $direccion->direccion = $linea['Dirección'];
                           $direccion->codpostal = $linea['C.P.'];
                           $direccion->ciudad = $linea['Población'];
                           $direccion->provincia = $linea['Provincia'];
                           $direccion->save();
                        }
                     }
                     else
                     {
                        $this->new_error_msg('Error al guardar los datos del proveedor.');
                     }
                  }
               }
               else
               {
                  $plinea = $this->custom_explode($_POST['separador'], $aux);

                  $columnas = "Cód.;Nombre;Dirección;C.P.;Población;Provincia;Teléfono;N.I.F.";
                  if(!$this->validar_columnas($plinea, $this->custom_explode(';', $columnas)))
                  {
                     $this->new_error_msg('El archivo no contiene las columnas necesarias.');
                     break;
                  }
               }
            }
         }

         $this->new_message($total . ' proveedores importados.');
      }
   }

   private function importar_familias()
   {
      $plinea = FALSE;
      $total = 0;
      $numlinea = 0;
      $ultimoregistro = false;

      $fcsv = fopen($_FILES['fcsv']['tmp_name'], 'r');
      if($fcsv)
      {
         while(!feof($fcsv)) {
            $aux = trim(fgets($fcsv));
            $numlinea++;
            if($aux != '')
            {
               if($ultimoregistro)
               {
                  //Si no es el ultimo registro nos salimos del bucle y mandamos un error
                  $this->new_error_msg('Se necesita un Código de familia (Registro número ' . ($numlinea - 1) . ').');
                  break;
               }

               if($plinea)
               {

                  $linea = array();
                  foreach($this->custom_explode($_POST['separador'], $aux) as $i => $value)
                  {
                     if($i < count($plinea))
                     {
                        $linea[$plinea[$i]] = $value;
                     }
                  }

                  //Código;Descripción;Sección

                  if(!isset($linea['Código']))
                  {
                     $ultimoregistro = true;
                     continue;
                  }

                  /// ¿Existe la familia?
                  $sql = "SELECT * FROM familias  WHERE codfamilia = ".$this->empresa->var2str($linea['Código']).";";
                  $data = $this->db->select($sql);
                  if(count($linea) == count($plinea) AND ( !$data OR isset($_POST['sobreescribir'])))
                  {
                     if($data AND isset($_POST['sobreescribir']))
                     {
                        $familia = new familia($data[0]);
                     }
                     else
                     {
                        $familia = new familia();
                        $familia->codfamilia = $linea['Código'];
                     }

                     $familia->descripcion = $linea['Descripción'];

                     if($linea['Sección'] != '')
                     {
                        if($linea['Sección'] != $familia->codfamilia)
                        {
                           $familia->madre = $linea['Sección'];
                        }
                     }

                     if($familia->save())
                     {
                        $total++;
                     }
                     else
                        $this->new_error_msg('Error al guardar los datos de la familia.');
                  }
               }
               else
               {
                  $plinea = $this->custom_explode($_POST['separador'], $aux);

                  /// validamos las columnas
                  //Código;Descripción;Sección
                  if(!$this->validar_columnas($plinea, $this->custom_explode(';', "Código;Descripción;Sección")))
                  {
                     $this->new_error_msg('El archivo no contiene las columnas necesarias.');
                     break;
                  }
               }
            }
         }

         $this->new_message($total . ' familias importadas.');
         $this->cache->clean();
      }
   }

   private function importar_articulos()
   {
      $plinea = FALSE;
      $imp0 = new impuesto();
      $impuestos = $imp0->all();
      $total = 0;
      $numlinea = 0;
      
      $fcsv = fopen($_FILES['fcsv']['tmp_name'], 'r');
      if($fcsv)
      {
         while(!feof($fcsv)) {
            $aux = trim(fgets($fcsv));
            $numlinea ++;
            if($aux != '')
            {
               if($plinea)
               {
                  $linea = array();
                  foreach($this->custom_explode($_POST['separador'], $aux) as $i => $value)
                  {
                     if($i < count($plinea))
                     {
                        $linea[$plinea[$i]] = $value;
                     }
                  }

                  /// ¿Existe el artículo?
                  $sql = "SELECT * FROM articulos";
                  if($linea['Código'])
                  {
                     $sql .= " WHERE referencia = ".$this->empresa->var2str($linea['Código']).";";
                     $data = $this->db->select($sql);
                     if(count($linea) == count($plinea))
                     {
                        if(!$data OR isset($_POST['sobreescribir']))
                        {
                           if($data AND isset($_POST['sobreescribir']))
                           {
                              $articulo = new articulo($data[0]);
                           }
                           else
                           {
                              $articulo = new articulo();
                              $articulo->referencia = $linea['Código'];
                           }

                           $articulo->descripcion = $linea['Descripción'];
                           $articulo->set_pvp(floatval($linea['Venta']));
                           $articulo->costemedio = $articulo->preciocoste = floatval($linea['Costo']);

                           foreach($impuestos as $imp)
                           {
                              $articulo->codimpuesto = $imp->codimpuesto;
                              break;
                           }

                           if($articulo->save())
                           {
                              $total++;
                              $articulo->set_stock($this->empresa->codalmacen, floatval($linea['Stock()']));
                           }
                           else
                           {
                              $this->new_error_msg('Error al guardar los datos del artículo (Registro: ' . $linea['Código'] . ').');
                              break;
                           }
                        }
                     }
                     else
                     {
                        $this->new_error_msg('Error en el número de campos (Registro: ' . $linea['Código'] . '). (' . $numlinea . ')');
                     }
                  }
               }
               else
               {
                  $plinea = $this->custom_explode($_POST['separador'], $aux);

                  if(!$this->validar_columnas($plinea, $this->custom_explode(';', "Código;Descripción;Ref.prov;Prov.;Costo;Venta;Stock();Margen;Real")))
                  {
                     $this->new_error_msg('El archivo no contiene las columnas necesarias.');
                     break;
                  }
               }
            }
         }

         $this->new_message($total . ' artículos importados.');
      }
   }

   private function validar_columnas($cols, $valids)
   {
      $result = TRUE;

      if(is_array($cols) AND is_array($valids))
      {
         foreach($valids as $val)
         {
            if(!in_array($val, $cols))
            {
               $this->new_error_msg('Falta la columna ' . $val);
               $result = FALSE;
               break;
            }
         }
      }
      else
      {
         $result = FALSE;
      }

      return $result;
   }

   /**
    * Devuelve un array con los resultados después de partir una cadena usando
    * el separador $separador.
    * Tiene en cuenta los casos en que la subcadena empieza por comillas y
    * contiene el separadr dentro, como cuando exportas de excel o libreoffice:
    * columna1;"columna2;esto sigue siendo la columna 2";columna3
    * 
    * @param type $separador
    * @param type $texto
    * @return type
    */
   private function custom_explode($separador, $texto)
   {
      $seplist = array();

      if(mb_detect_encoding($texto, 'UTF-8', TRUE) === FALSE)
      {
         /// si no es utf8, convertimos
         $texto = utf8_encode($texto);
      }

      $aux = explode($separador, $texto);
      if($aux)
      {
         $agrupar = '';

         foreach($aux as $a)
         {
            if($agrupar != '')
            {
               /// continuamos agrupando
               $agrupar .= $separador . $a;

               if(substr($a, -1) == '"')
               {
                  /// terminamos de agrupar
                  $seplist[] = trim(substr($agrupar, 0, -1));
                  $agrupar = '';
               }
            }
            else if(substr($a, 0, 1) == '"' AND substr($a, -1) != '"')
            {
               /// empezamos a agrupar
               $agrupar = substr($a, 1);
            }
            else if(substr($a, 0, 1) == '"' AND substr($a, -1) == '"')
            {
               $seplist[] = trim(substr($a, 1, -1));
            }
            else
               $seplist[] = trim($a);
         }
      }

      return $seplist;
   }
}
