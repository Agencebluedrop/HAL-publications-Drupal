/**
 * @file
 */

(function ($) {
  $(document).ready(function () {
    createCheckboxes();
    checkAll();
    checkForChecked();
    applyChosen();
    $("#edit_year_chosen").width($("#edit_author_chosen").width());
    addPublicationIds();
  });

  // FUNCTION TO APPLY CHOSEN JS ON SELECT LISTS.
  function applyChosen(){
    if ($("#edit-year")) {
      $('#edit-year').chosen({
        placeholder_text_multiple: "Année"
      });
    }
    if ($("#edit-author")) {
      $('#edit-author').chosen({
        placeholder_text_multiple: "Auteur"
      });
    }
  }

  // FUNCTION TO CREATE CHECKBOXES NEAR EACH PUBLICATION.
  function createCheckboxes(){
    if ($(".hal-publications")) {
      if ($(".hal-publication").length > 0) {
        $(".hal-publications").prepend("<input type='checkbox' id='check-all'/><span>Tout sélectionner</span>");
      }
      $(".hal-publication").each(function () {
        $(this).prepend("<input type='checkbox' id='" + $(this).attr("id") + "'/>");
      });
      $(".toggle-export").hide();
    }
  }

  // FUNCTION TO CHECK/UNCHECK ALL CHECKBOXES WHEN THE SELECT ALL CHECKBOX IS CHECKED/UNCHECKED.
  function checkAll(){
    $("#check-all").click(function () {
      $("input[type=checkbox]").prop('checked', $(this).prop('checked'));
      $(this).toggleClass("hal-checked");
      toggleExport();
    });
  }

  // FUNCTION TO ADD CHECKED PUBLICATION IDS TO THE HIDDEN INPUT.
  function addPublicationIds(){
    $('input[type="checkbox"]').click(function () {
      var ids = "";
      $('input[type="checkbox"]').not("#check-all").each(function () {
          if ($(this).is(":checked")) {
              ids += $(this).attr("id") + "&";
          }
      });
      ids = ids.substring(0, ids.length - 1);
      $('input[name="publications-ids"]').val(ids);
    });
  }

  // FUNCTION TO CHECK IF EXPORT LINKS SHOULD APPEAR UPON CKILICKING ON ANY CHECKBOX.
  function checkForChecked(){
      $('input[type="checkbox"]').click(function () {
        toggleExport();
      });
  }

  // FUNCTION TO CHECK IF EXPORT LINKS SHOULD APPEAR OR NOT.
  function toggleExport(){
    var anyBoxesChecked = false;
    $('input[type="checkbox"]').each(function () {
        if ($(this).is(":checked")) {
            anyBoxesChecked = true;
        }
    });
    if (anyBoxesChecked == true) {
      $(".toggle-export").show();
    }
    else {
      $(".toggle-export").hide();
    }
  }

}(jQuery));
