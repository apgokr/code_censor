(function ($) {
  'use strict';

  Drupal.behaviors.test_module_2 = {
    attach: function(context, settings) {
      $('#blocktabs-search_results_tabs', context).once('searchResults').each(function () {
        // If 'News' tab is selected then only show sort by filters.
        var news_text = Drupal.t('News');
        var news_tab = $(this).find('.ui-tabs-nav a:contains(' + news_text + ')').parent('li');
        var sort_filter = $('.region-content #edit-sort-bef-combine');

        sort_filter.hide();
        if (news_tab.hasClass("ui-tabs-active")) {
          sort_filter.show();
        }
        $(this).find('.ui-tabs-nav a').on('click', function() {
          if ($(this).text() == news_text) {
            sort_filter.show();
          }
          else {
            sort_filter.hide();
          }
        });

      });

      // Add a per doc type class for each Document Results.
      $(".view-global-search .views-field-name .field-content").each(function() {
        if (!$(this).is(':empty')) {
          $(this).addClass('row-doc');
          var link = $(this).find('a').attr('href');
          if (link) {
            var ext = link.substr((link.lastIndexOf('.')) +1);
            $(this).addClass('doc-type-' + ext);
            // Fix Link for excerpt field for Document rows.
            $(this).closest('.views-row').find('.views-field-search-api-excerpt a').attr('href', link);
          }
        }
      });

      // Show image search Results in All Tab.
      $('.view-display-id-global_search_block_all', context).once('copyImageResults').each(function () {
        let first_page = $(this).find('nav[role="navigation"] li').first().text().trim().match('^Page ');
        if (first_page && $(this).find('.flickr_row').length == 0) {
          let flickr_images = $('.flickrimages .photo-display-container .photo-display-item').slice(0, 4).clone();
          if (flickr_images.length > 0) {
            let title = Drupal.t('Images for ') + $('#edit-keyword').val();
            let relevance = $(this).find('.views-row').eq(1).find('.views-field-search-api-relevance .field-content').text();
            let image_result_row = $('<div class="views-row flickr-row"><div class="title"><a href="#">' + title + '</a></div><div class="relevance">' + relevance + '</div></div>');
            image_result_row.append(flickr_images);
            $(this).find('.views-row:eq(1)').after(image_result_row);
            // Add handler to switch to image tab.
            $(this).on('click', '.flickr-row .title a', function(e) {
              e.preventDefault();
              $("#blocktabs-search_results_tabs").tabs("option", "active", 3);
            });
          }
        }
      });

      // Hide unwanted empty rows & open document rows in new tab.
      $('.view-display-id-global_search_block_all .views-field-name .field-content').each(function(){
        if ($(this).text().trim().length == 0) {
          $(this).closest('.views-field-name').addClass('empty');
        }
        else {
          $(this).closest('.views-row').find('a').each(function() {
            $(this).attr('target', '_blank');
          })
        }
      });

    }
  };
}(jQuery));
