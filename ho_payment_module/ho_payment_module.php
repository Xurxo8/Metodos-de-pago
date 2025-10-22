<?php
/**
* 2007-2025 PrestaShop
*
* AVISO DE LICENCIA
*
* Este archivo fuente está sujeto a la Licencia Académica Libre (AFL 3.0)
* que se incluye con este paquete en el archivo LICENSE.txt.
* También está disponible a través de la web en la siguiente URL:
* http://opensource.org/licenses/afl-3.0.php
* Si no ha recibido una copia de la licencia y no puede
* obtenerla a través de Internet, envíe un correo electrónico a
* license@prestashop.com para que podamos enviársela de inmediato.
*
* DESCARGO DE RESPONSABILIDAD
*
* No edite ni agregue a este archivo si desea actualizar PrestaShop a versiones más recientes.
* Si desea personalizar PrestaShop según sus necesidades,
* consulte http://www.prestashop.com para obtener más información.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2025 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Licencia Académica Libre (AFL 3.0)
*  Marca registrada internacional y propiedad de PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
  exit;
}

class Ho_payment_module extends Module{
  protected $config_form = false;
  protected $metodoEditar = null;

  public function __construct(){
    $this->name = 'ho_payment_module';
    $this->tab = 'administration';
    $this->version = '1.0.0';
    $this->author = 'Xurxo';
    $this->need_instance = 0;

    /**
     * Establece $this->bootstrap en true si tu módulo es compatible con bootstrap (PrestaShop 1.6)
     */
    $this->bootstrap = true;

    parent::__construct();

    $this->displayName = $this->l('hoPaimentModule');
    $this->description = $this->l('Módulo donde se mostrarán las formas de pago');

    $this->confirmUninstall = $this->l('¿Seguro que quieres desinstalar?');

    $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '9.0');
  }

  /**
   * No olvides crear los métodos de actualización si es necesario:
   * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
   */
  public function install(){

    return parent::install() &&
      $this->registerHook('header') &&
      $this->registerHook('displayBackOfficeHeader') &&
      $this->registerHook('displayFooter');
  }

  public function uninstall(){
    return parent::uninstall();
  }

  /**
   * Eliminar métodos de pago
   */
  protected function deletePaymentMethod($id){
    $metodosJSON = Configuration::get('HO_PAYMENT_MODULE_PAYMENTS');
    $metodos = $metodosJSON ? json_decode($metodosJSON, true) : [];

    // Buscar el método que vamos a eliminar para borrar su logo
    foreach ($metodos as $metodo) {
      if ($metodo['id'] == $id && !empty($metodo['logo'])) {
        $logoPath = $this->local_path . 'views/img/' . $metodo['logo'];
        if (file_exists($logoPath)) {
          unlink($logoPath); // Borra la imagen del servidor
        }
        break;
      }
    }

    // Filtramos los que no coinciden con el ID
    $metodos = array_filter($metodos, function ($metodo) use ($id) {
      return $metodo['id'] != $id;
    });

    // Reindexamos el array
    $metodos = array_values($metodos);

    // Guardamos JSON actualizado
    Configuration::updateValue('HO_PAYMENT_MODULE_PAYMENTS', json_encode($metodos));

    // Mensaje de confirmación
    $this->context->controller->confirmations[] = $this->l('Método de pago eliminado correctamente.');
  }

  /**
   * Carga el formulario de configuración
   */
  public function getContent(){
    /**
     * Si se han enviado valores en el formulario, los procesa.
     */
    if (((bool)Tools::isSubmit('submitHo_payment_moduleModule')) == true) {
      $this->postProcess();
    }

    // Eliminar método de pago si se pasa borrar=id
    if ($borrarId = Tools::getValue('delete')) {
      $this->deletePaymentMethod((int)$borrarId);
    }

    // Asignaciones a smrty
    $this->context->smarty->assign('module_dir', $this->_path);

    // Cargar datos almacenados para mostrarlos en el formulario
    $metodosJSON = Configuration::get('HO_PAYMENT_MODULE_PAYMENTS');
    $metodos = $metodosJSON ? json_decode($metodosJSON, true) : [];

    // Asignar variables a Smarty antes de renderizar
    $this->context->smarty->assign([
      'module_dir' => $this->_path,
      'metodos' => $metodos,
      'module' => $this,
      'link' => $this->context->link,
    ]);

    // Modificación de los módulos creados
    $editId = Tools::getValue('edit');
    $metodoEditar = null; // inicializamos siempre

    if($editId){
      foreach ($metodos as $metodo) {
        if ($metodo['id'] == $editId) {
          $metodoEditar = $metodo;
          break;
        }
      }
    }

    // Guardar "metodoEditar" como propiedad de la clase para usarlo en getConfigFormValues()
    $this->metodoEditar = $metodoEditar;

    // Rendecizar plantilla (TPL)
    $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

    // Renderizar formulario + TPL
    return $this->renderForm().$output;
  }

  /**
   * Crea el formulario que se mostrará en la configuración del módulo.
   */
  protected function renderForm(){
    $helper = new HelperForm();

    // configuración básica
    $helper->show_toolbar = false;
    $helper->table = $this->table;
    $helper->module = $this;
    $helper->default_form_language = $this->context->language->id;
    $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

    // Identificadores y acción del formulario
    $helper->identifier = $this->identifier;
    $helper->submit_action = 'submitHo_payment_moduleModule';

    // currentIndex y token usando context ya inicializado en el back office
    $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
      .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');

    // Valores para los campos del formulario
    $helper->tpl_vars = array(
      'fields_value' => $this->getConfigFormValues(), /* Añade valores para los campos del formulario */
      'languages' => $this->context->controller->getLanguages(),
      'id_language' => $this->context->language->id,
    );

    return $helper->generateForm(array($this->getConfigForm()));
  }

  /**
   * Crea la estructura del formulario.
   */
  protected function getConfigForm(){
    $form = array(
      'form' => array(
        'legend' => array(
          'title' => $this->l('Configuración de los métodos de pago'),
          'icon' => 'icon-credit-card',
        ),
        'input' => array(
          array(
            'col' => 4,
            'type' => 'text',
            'label' => $this->l('Nombre: '),
            'name' => 'HO_PAYMENT_MODULE_NAME',
            'required' => true,
            'desc' => $this->l('Introduce el nombre que se mostrará al cliente (por ejemplo: “PayPal” o “Bizum”).'),
          ),
          array(
            'type' => 'file',
            'label' => $this->l('Logo: '),            
            'name' => 'HO_PAYMENT_MODULE_LOGO',
            'required' => true,
            'desc' => $this->l('Sube una imagen para el logo del método de pago (formato: SVG o WEBP).'),
            'display_image' => true,
            'image' => (isset($this->metodoEditar['logo']) && $this->metodoEditar['logo'])
              ? '<img src="'.$this->_path.'views/img/'.$this->metodoEditar['logo'].'" height="80" />'
              : '',
          ),
        ),
        'submit' => array(
          'title' => $this->l('Guardar'),
        ),
      ),
    );

    // Si estamos editando un método, añadimos un campo hidden
    if (isset($this->metodoEditar) && $this->metodoEditar) {
      $form['form']['input'][] = [
        'type' => 'hidden',
        'name' => 'EDIT_ID',
      ];
    }

    return $form;
  }

  /**
   * Define los valores de los campos del formulario.
   */
  protected function getConfigFormValues(){
    if(isset($this->metodoEditar) && !empty($this->metodoEditar)){
      return [
        'HO_PAYMENT_MODULE_NAME' => $this->metodoEditar['nombre'],
        'HO_PAYMENT_MODULE_LOGO' => $this->metodoEditar['logo'],
        'EDIT_ID' => $this->metodoEditar['id'],
      ];
    }

    return array(
      'HO_PAYMENT_MODULE_NAME' => '',
      'HO_PAYMENT_MODULE_LOGO' => '',
      'EDIT_ID' => '',
    );
  }

  /**
   * Guarda los datos del formulario.
   */
  protected function postProcess(){
    $errors = [];
    $nombre = Tools::getValue('HO_PAYMENT_MODULE_NAME');
    $editId = Tools::getValue('EDIT_ID'); // ✅ CORREGIDO: el campo oculto se llama EDIT_ID
    $metodosJSON = Configuration::get('HO_PAYMENT_MODULE_PAYMENTS');
    $metodos = $metodosJSON ? json_decode($metodosJSON, true) : [];

    // =========================
    // VALIDACIÓN DEL NOMBRE
    // =========================
    if (empty($nombre)) {
      $errors[] = $this->l('El nombre del método de pago no puede estar vacío.');
    } elseif (!Validate::isGenericName($nombre)) {
      $errors[] = $this->l('El nombre del método de pago contiene caracteres no válidos.');
    } elseif (strlen($nombre) > 50) {
      $errors[] = $this->l('El nombre del método de pago no puede superar los 50 caracteres.');
    } elseif (!$editId && in_array($nombre, array_column($metodos, 'nombre'))) {
      $errors[] = $this->l('Ya existe un método con ese nombre.');
    }

    // =========================
    // PROCESO DE LA IMAGEN (LOGO)
    // =========================
    $nombreLogo = '';
    $esEdicion = !empty($editId);
    $logoSubido = isset($_FILES['HO_PAYMENT_MODULE_LOGO']) && !empty($_FILES['HO_PAYMENT_MODULE_LOGO']['tmp_name']);

    if (!$logoSubido && !$esEdicion) {
      $errors[] = $this->l('Debes subir un logo para el método de pago.');
    }

    if ($logoSubido) {
      $logo = $_FILES['HO_PAYMENT_MODULE_LOGO'];
      $tipoImgValido = ['image/svg+xml', 'image/webp'];

      if (!in_array($logo['type'], $tipoImgValido)) {
        $errors[] = $this->l('Formato de imagen no válido. Solo se permiten SVG o WEBP.');
      }

      if ($logo['size'] > 2 * 1024 * 1024) {
        $errors[] = $this->l('El archivo del logo es demasiado grande. Tamaño máximo permitido: 2 MB.');
    }

    if (empty($errors)) {
      // Ruta correcta dentro del módulo
      $path = $this->local_path . 'views/img/';
      if (!file_exists($path)) {
        mkdir($path, 0755, true);
      }

      $nombreFormulario = preg_replace('/[^a-zA-Z0-9_-]/', '', $nombre);
      $nombreFormulario = substr($nombreFormulario, 0, 20);
      $nombreLogo = $nombreFormulario . '_' . uniqid() . '.' . pathinfo($logo['name'], PATHINFO_EXTENSION);

      if (!move_uploaded_file($logo['tmp_name'], $path . $nombreLogo)) {
        $errors[] = $this->l('Error al subir el logo. Verifica los permisos del directorio.');
      }
    }
    } elseif ($esEdicion) {
      foreach ($metodos as $metodo) {
        if ($metodo['id'] == $editId) {
          $nombreLogo = $metodo['logo'];
          break;
        }
      }
    }

    // =========================
    // MOSTRAR ERRORES Y SALIR
    // =========================
    if (!empty($errors)) {
      foreach ($errors as $error) {
        $this->context->controller->errors[] = $error;
      }
      return;
    }

    // =========================
    // GUARDAR DATOS
    // =========================
    if ($esEdicion) {
      foreach ($metodos as &$metodo) {
        if ($metodo['id'] == $editId) {
          $metodo['nombre'] = $nombre;
          $metodo['logo'] = $nombreLogo;
          break;
        }
      }
    } else {
      $nuevoID = count($metodos) ? max(array_column($metodos, 'id')) + 1 : 1;
      $metodos[] = [
        'id' => $nuevoID,
        'nombre' => $nombre,
        'logo' => $nombreLogo
      ];
    }

    Configuration::updateValue('HO_PAYMENT_MODULE_PAYMENTS', json_encode($metodos));
    $this->context->controller->confirmations[] = $this->l('Método de pago guardado correctamente.');
  }

  /**
  * Añade los archivos CSS y JavaScript que se cargarán en el panel de administración.
  */
  public function hookDisplayBackOfficeHeader(){
    if (Tools::getValue('configure') == $this->name) {
    $this->context->controller->addJS($this->_path.'views/js/back.js');
    $this->context->controller->addCSS($this->_path.'views/css/back.css');
    }
  }

  /**
   * Añade los archivos CSS y JavaScript que se cargarán en el front-office.
   */
  public function hookHeader(){
    $this->context->controller->addJS($this->_path.'/views/js/front.js');
    $this->context->controller->addCSS($this->_path.'/views/css/front.css');
  }

  public function hookDisplayFooter(){
    // Obtener métodos de pago guardados
    $metodosJSON = Configuration::get('HO_PAYMENT_MODULE_PAYMENTS');
    $metodos = $metodosJSON ? json_decode($metodosJSON, true) : [];

    // Asignar variables a Smarty
    $this->context->smarty->assign([
      'ho_payment_metodos' => $metodos,
      'module_dir' => $this->_path . 'views/img' // ruta de imágenes del módulo
    ]);

    // Renderizar plantilla del front
    return $this->display(__FILE__, 'views/templates/front/hoPaymentModule.tpl');
  }
}
