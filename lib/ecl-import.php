<?php

// Published under GPL
// tutorial here: https://codeable.io/community/how-to-import-json-into-wordpress/
class ECL_Import {

  public function __construct() {

    add_action('wp_ajax_import_ecl', array($this, 'import_ecl'));
    add_action('wp_ajax_nopriv_import_ecl', array($this, 'import_ecl'));

  }

  public function import_ecl() {

    // ECL Article API URL.
    $ecl_data = json_decode(file_get_contents('http://7da-ecl.chromatic.is/jsonapi/node/article?_format=api_json'), true);

    //if ($this->compare_keys()) {
      $this->insert_or_update($ecl_data);
    //}

    wp_die();

  }

  private function insert_or_update($ecl_data) {

    // If no data is found, bail out.
    if (!$ecl_data) {
      return false;
    }

    // Loop though all ECL posts and import into WP.
    foreach($ecl_data['data'] as $data) {

      // Search by the custom field 'ecl_id' which stores a unique id from the
      // ECL article API.
      $args = array(
        'meta_query' => array(
          array(
            'key'   => 'ecl_id',
            'value' => '00' . $data['attributes']['nid']
          )
        ),
        'post_type'      => 'post',
        'post_status'    => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'),
        'posts_per_page' => 1
      );

      $ecl = get_posts($args);

      // Set the post ID to nothing to start with.
      $ecl_id = '';

      // If ECL imported data exists in WP, set the post ID so the post can be
      // updated and not newly inserted.
      if ($ecl) {
        $ecl_id = $ecl[0]->ID;
      }

      // Create the post.
      $ecl_post = array(
        'ID'            => $ecl_id,
        'post_title'    => $data['attributes']['title'],
        'post_content'  => $data['attributes']['field_article_subtitle'],
        'post_author'   => 1,
        'post_status'   => 'publish',
        'post_category' => array(14)
      );

      // Insert or update the posts.
      $ecl_id = wp_insert_post($ecl_post);

      // If no ecl_id exists, add it to the custom 'ecl_id field.'
      if ($ecl_id) {
        // Add the "ecl_id" to the custom field to check against to ensure we're
        // updating or posting a whole new post.
        update_post_meta($ecl_id, 'ecl_id', '00' . $data['attributes']['nid']);
      }
    }

    print_r($ecl_id);

  }

  private function compare_keys() {

    // Signature should be in a form of algorihm=hash
    // for example: X-Codeable-Signature: sha1=246d2e58593645b1f261b1bbc867fe2a9fc1a682
    if (!isset($_SERVER['HTTP_X_CODEABLE_SIGNATURE'])) {
      throw new \Exception( "HTTP header 'X-Codeable-Signature' is missing.");
    }

    list($algo, $hash) = explode('=', $_SERVER['HTTP_X_CODEABLE_SIGNATURE'], 2) + array('', '');
    $raw_post = file_get_contents('http://7da-ecl.chromatic.is/jsonapi/node/article?_format=api_json');

    // Don't forget to define your key!
    if ($hash !== hash_hmac($algo, $raw_post, CODEABLE_KEY)) {
      throw new \Exception('Secret hash does not match.');
    }

    return true;

  }

}

new ECL_Import();
