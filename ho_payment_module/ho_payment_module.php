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
    Configuration::updateValue('HO_PAYMENT_MODULE_LIVE_MODE', false);

    return parent::install() &&
      $this->registerHook('header') &&
      $this->registerHook('displayBackOfficeHeader') &&
      $this->registerHook('displayFooter');
  }

  public function uninstall(){
    Configuration::deleteByName('HO_PAYMENT_MODULE_LIVE_MODE');

    return parent::uninstall();
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

    $this->context->smarty->assign('module_dir', $this->_path);

    $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

    return $this->renderForm().$output;
  }

  /**
   * Crea el formulario que se mostrará en la configuración del módulo.
   */
  protected function renderForm(){
    $helper = new HelperForm();

    $helper->show_toolbar = false;
    $helper->table = $this->table;
    $helper->module = $this;
    $helper->default_form_language = $this->context->language->id;
    $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

    $helper->identifier = $this->identifier;
    $helper->submit_action = 'submitHo_payment_moduleModule';
    $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
      .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');

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
    return array(
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
            'reqiured' => true,
            'desc' => $this->l('Introduce el nombre que se mostrará al cliente (por ejemplo: “PayPal” o “Bizum”).'),
          ),
          array(
            'type' => 'file',
            'label' => $this->l('Logo: '),            
            'name' => 'HO_PAYMENT_MODULE_LOGO',
            'desc' => $this->l('Sube una imagen para el logo del método de pago (formato: SVG o WEBP).'),
            'display_image' => true,
            'image' => (Configuration::get('HO_PAYMENT_MODULE_LOGO'))
            ? '<img src="'.$this->_path.'views/img/'.Configuration::get('HO_PAYMENT_MODULE_LOGO').'" height="80" />'
            : '',
          ),
        ),
        'submit' => array(
          'title' => $this->l('Guardar'),
        ),
      ),
    );
  }

  /**
   * Define los valores de los campos del formulario.
   */
  protected function getConfigFormValues(){
    return array(
    'HO_PAYMENT_MODULE_NAME' => '',
    'HO_PAYMENT_MODULE_LOGO' => '',
  );
  }

  /**
   * Guarda los datos del formulario.
   */
  protected function postProcess(){
    $errors = [];
    $nombre = Tools::getValue('HO_PAYMENT_MODULE_NAME');

    // Validar nombre
    if (empty($nombre) || !Validate::isGenericName($nombre)) {
      $errors[] = $this->l('El nombre del método de pago no es válido o está vacío.');
    }

    // Procesar logo si se sube
    $nombreLogo = '';
    if (isset($_FILES['HO_PAYMENT_MODULE_LOGO']) && !empty($_FILES['HO_PAYMENT_MODULE_LOGO']['tmp_name'])) {
      $logo = $_FILES['HO_PAYMENT_MODULE_LOGO'];
      $tipoImgValido = ['image/svg+xml', 'image/webp'];

      // Validar tipo MIME
      if (!in_array($logo['type'], $tipoImgValido)) {
        $errors[] = $this->l('Formato de imagen no válido. Solo se permiten SVG o WEBP.');
      } else {
        $path = $this->local_path . 'views/img/';
        if (!file_exists($path)) {
          mkdir($path, 0755, true);
        }

        // Recoger nombre del formulario y limpiar caracteres no permitidos
        $nombreFormulario = preg_replace('/[^a-zA-Z0-9_-]/', '', Tools::getValue('HO_PAYMENT_MODULE_NAME'));

        // Truncarlo si es demasiado largo (max. 20 caracteres)
        $nombreFormulario = substr($nombreFormulario, 0, 20);

        // Generar nombre único para el logo
        $nombreLogo = $nombreFormulario . '_' . uniqid() . '.' . pathinfo($_FILES['HO_PAYMENT_MODULE_LOGO']['name'], PATHINFO_EXTENSION);

        // Mover el archivo subido
        if (!move_uploaded_file($logo['tmp_name'], $path . $nombreLogo)) {
          $errors[] = $this->l('Error al subir el logo. Verifica los permisos del directorio.');
        }
      }
    }

    // Guardar en JSON si no hay errores
    if (empty($errors)) {
      // Recuperar métodos existentes
      $metodosJSON = Configuration::get('HO_PAYMENT_MODULE_PAYMENTS');
      $metodos = $metodosJSON ? json_decode($metodosJSON, true) : []; 

      // Generar nuevo ID incremental
      $nuevoID = count($metodos) ? max(array_column($metodos, 'id')) + 1 : 1;

      // Crear nuevo método
      $nuevoMetodo = [
        'id' => $nuevoID,
        'nombre' => $nombre,
        'logo' => $nombreLogo
      ];

      $metodos[] = $nuevoMetodo;

      // Guardar todo el JSON actualizado
      Configuration::updateValue('HO_PAYMENT_MODULE_PAYMENTS', json_encode($metodos));

      return $this->displayConfirmation($this->l('Método de pago guardado correctamente.'));
    } else {
      return $this->displayError(implode('<br>', $errors));
    }
  }

  /**
  * Añade los archivos CSS y JavaScript que se cargarán en el panel de administración.
  */
  public function hookDisplayBackOfficeHeader()
  {
    if (Tools::getValue('configure') == $this->name) {
      $this->context->controller->addJS($this->_path.'views/js/back.js');
      $this->context->controller->addCSS($this->_path.'views/css/back.css');
    }
  }

  /**
   * Añade los archivos CSS y JavaScript que se cargarán en el front-office.
   */
  public function hookHeader()
  {
    $this->context->controller->addJS($this->_path.'/views/js/front.js');
    $this->context->controller->addCSS($this->_path.'/views/css/front.css');
  }

  public function hookDisplayFooter()
  {
    /* Inserta aquí tu código. */
  }
}
