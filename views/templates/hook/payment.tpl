<p class="payment_module">
    <form action="{$action}" id="paypalForm" method="post">
    <input type="hidden" name="total" value="{$total_price}">
    <input type="hidden" name="currency" value="{$currency->iso_code}">
    <a href="#paypalForm" onClick="document.getElementById('paypalForm').submit(); return false;" title="{l s='Pay with your card or your PayPal account' mod='cheque'}">
        <img src="/modules/paypal/logo.jpg" alt="Pay with your card or your PayPal account" />
        {l s='Pay with your card or your PayPal account' mod='paypal'}
    </a>
    </form>
</p>
{debug}