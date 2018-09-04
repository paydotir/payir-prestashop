
<!-- Pay.ir Payment Module -->
<p class="payment_module">
  <a href="javascript:$('#payir').submit();" title="Online payment with Pay.ir">
    <img src="https://pay.ir/images/logo.png" alt="Online payment with Pay.ir" style="margin-left:20px;" />
    پرداخت از طریق درگاه پرداخت و کیف پول الکترونیک Pay.ir
  </a>
</p>
<form id="payir" action="modules/payir/process.php?do=payment" method="post" class="hidden">
  <input type="hidden" name="orderId" value="{$orderId}" />
</form>
<!-- End of Pay.ir Payment Module-->
