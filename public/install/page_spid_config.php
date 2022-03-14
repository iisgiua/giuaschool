<?php include('page_header.php'); ?>

<form name="install" method="post">
  <div class="form-group col text-center">
    <button type="submit" id="install_submit" name="install[<?php echo $page['submitType']; ?>]" class="btn-primary mt-1 btn">Vai avanti</button>
  </div>
  <input type="hidden" id="install_xml" name="install[xml]" value="">
  <input type="hidden" id="install_step" name="install[step]" value="<?php echo $this->step; ?>">
  <input type="hidden" id="install__token" name="install[_token]" value="<?php echo $page['_token']; ?>">
</form>

<?php include('page_footer.php'); ?>
