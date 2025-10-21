{*
* 2007-2025 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2025 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<div class="panel">
  <h3>{$module->l('Métodos de pago configurados')}</h3>

  {if $metodos|@count > 0}
    <table class="table" style="width:100%; margin-top:10px;">
      <thead>
        <tr>
          <th>{$module->l('ID')}</th>
          <th>{$module->l('Nombre')}</th>
          <th>{$module->l('Logo')}</th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$metodos item=metodo}
          <tr>
            <td>{$metodo.id}</td>
            <td>{$metodo.nombre}</td>
            <td>
              {if $metodo.logo}
                <img src="{$module_dir}views/img/{$metodo.logo}" alt="{$metodo.nombre}" height="50">
              {/if}
            </td>
          </tr>
        {/foreach}
      </tbody>
    </table>
  {else}
    <p>{$module->l('No hay métodos de pago configurados aún.')}</p>
  {/if}
</div>

