<div id="metodosPagoWrapper">
    <h3><u>Elige tu método de pago</u></h3>

    {if $ho_payment_metodos|@count > 0}
        <div class="contenedorMetodosPago">
            {foreach from=$ho_payment_metodos item=metodo}
                <div class="metodo">
                    <img src="{$module_dir}views/img/{$metodo.logo}" alt="{$metodo.nombre}" height="50">
                </div>
            {/foreach}
        </div>
    {else}
        <p>No hay métodos de pago disponibles.</p>
    {/if}
</div>