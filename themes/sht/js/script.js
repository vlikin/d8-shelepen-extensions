(function(sht) {
  Drupal.behaviors.shtInitial = {
    attach: function (context, settings) {
      sht.materialize.AutoInit();
      console.log(sht);
    }
  };
})(sht);