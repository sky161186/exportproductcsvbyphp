<?php 
/*

Template Name: Import multiple Variation Product by CSV

*/



get_header();

if (isset($_FILES["csv"]["size"])) {

$file = $_FILES["csv"]["tmp_name"];
$handle = fopen($file,"r");

$row = 1;
$fields_name = array();
$fields_meta_name = array();

$product_data = array();

while (($data = fgetcsv($handle)) !== FALSE) {

$num = count($data);
$attr_start = array_search('prod_shape_default', $data);

if($row == 1){
  
            
  
    for($i=1; $i< $num ; $i++){
        
        if(strncmp("prodmeta_",$data[$i],8)==0)
           $fields_meta_name[$i]=$data[$i]; 

        $fields_name[$i]=$data[$i]; 
        
    }


    $tblname = 'productcustmeta';

    $wp_track_table = $table_prefix . "$tblname";

    if($wpdb->get_var( "show tables like '$wp_track_table'" ) != $wp_track_table) {

        $sql = "CREATE TABLE `".$wp_track_table."`(";

        $sql .= "  `id`  int(18) NOT NULL auto_increment, ";

        $sql .= "  `post_id`  int NOT NULL, ";

        if(count($fields_meta_name)){
          foreach ($fields_meta_name as $key=>$value) {        
              if($value)
                 $sql .= "  `$value`  varchar(20) NOT NULL, ";
          }
        } 


        $sql .= "  `status`  varchar(20) NOT NULL, ";

        $sql .= "  `update_date`  DATE, ";

        $sql .= " PRIMARY KEY `id` (`id`) ";

        $sql .= ");";

        require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );

        dbDelta($sql);

    }
    

   
}
else {


   foreach($fields_name as $key => $value) {
        $product_data[$value] = $data[$key];
       
    }


if($product_data['prod_name'])
  insert_product($product_data,$fields_meta_name);


}



$row++;

}
fclose($handle);

}

 //print_r($product_data);
?>



 

<style type="text/css">
#csv {border: 1px solid gainsboro;}
#importCsvFile{
background-color:white;
width: 100%;
height: 300px;
text-align: center;
border: 1px solid grey;
}
#importCsvFile h2{margin-bottom: 40px;margin-top: 40px;}

.bgimg {width: 100px ! important;display: inline ! important;margin-top: 0px ! important;}
</style>

 

<section id="promo"><div class="contentpane">
<article>

<div id="importCsvFile">
<form action="" method="post" enctype="multipart/form-data" name="form" id="form1">
<h2>Import Product csv file</h2>
<input accept="csv" name="csv" type="file" id="csv" />
<input type="submit" name="Submit" class="bgimg"/><br />

</form>
</div>

</article>

 
 

</div>
</section>

<?php





function insert_product ($product_data,$fields_meta_name)  
{

    
   
 
    $post = array( // Set up the basic post data to insert for our product

        'post_author'  => 1,
        'post_content' => $product_data['prod_long_desc'],
        "post_excerpt" => $product_data['prod_short_desc'],
        'post_status'  => 'publish',
        'post_title'   => $product_data['prod_name'],
        'post_parent'  => '',
        'post_type'    => 'product'
    );

    $post_id = wp_insert_post($post); // Insert the post returning the new post id
    $product = new WC_Product_Variable($post_id);
    $product->save();
    if (!$post_id) // If there is no post id something has gone wrong so don't proceed
    {
        return false;
    }


    
    update_post_meta( $post_id, "_stock_status", "instock");

    $sku = '';
    if($product_data['prod_sku_old'])
      $sku = $product_data['prod_sku_old'];
    else
      $sku = $product_data['prod_sku'];

    update_post_meta( $post_id, "_sku", $sku);
    update_post_meta( $post_id, "_tax_status", "taxable" );
    update_post_meta( $post_id, "_manage_stock", "no" );
    
   
    if(count($fields_meta_name)){
      $postCustMetaArr = array();
      $postCustMetaArr['post_id'] = $post_id;
      foreach ($fields_meta_name as $key=>$value) {        
          if($product_data[$value]){
            update_post_meta($post_id,$value,$product_data[$value]);
             $current_date = date('Y-m-d');
             $postCustMetaArr[$value] = $product_data[$value];
             
          }
      }

      global $wpdb;
       $table_name = $wpdb->prefix . "productcustmeta";
       $current_date = date('Y-m-d');
       $postCustMetaArr['stock_status'] = 'active';  
       $postCustMetaArr['update_date'] = $current_date; 

      $wpdb->insert($table_name, $postCustMetaArr);

    }  

  //echo $table_name.print_r($postCustMetaArr);

    if($product_data['prod_type'])
        create_category($post_id, $product_data['prod_type']);
    

    
    wp_set_object_terms($post_id, 'variable', 'product_type'); // Set it to a variable product type



    $available_attributes = array( "eo_metal_attr");
    $variations = array();
    $attr_val = '';
    $attr_common ='';
   

      $attr_arr = array('attr_14k', 'attr_18k', 'platinum');

      foreach ($attr_arr as $val) {
        $variation_regular = $val.'_regular';
        if($product_data[$variation_regular]){

           $regular_price  = $product_data[$variation_regular];
           $variation_sale = $val.'_sale';
           $sale_price  = ($product_data[$variation_sale])? $product_data[$variation_sale]: '';
           $attr_common = $val;
   
           $attr_name = str_replace('attr_', '', $val);
           $variations[] = array("attributes" => array(
                    "eo_metal_attr"  => $attr_name,                   
                ),
                "regular_price" => $regular_price,
                "sale_price"    => $sale_price
                
            ); 

            // Set default vaiation 
           if($val=='attr_14k'){
            $new_defaults = array('pa_eo_metal_attr'=>'14k'); 
            update_post_meta($post_id, '_default_attributes', $new_defaults);

           }
           

            insert_product_attributes($post_id, $available_attributes,$variations); // Add attributes passing the new post id, attributes & variations
            $variation_post_id = insert_product_variations($product, $post_id, $variations); // Insert variations passing the new post id & variations 

            if($variation_post_id){      

              update_post_meta($variation_post_id, 'attribute_pa_eo_metal_attr', $attr_name);
              update_post_meta($variation_post_id, '_price', $regular_price);
              update_post_meta($variation_post_id, '_regular_price', $regular_price);
              update_post_meta($variation_post_id, '_sale_price', $sale_price);

            }
            

            

          } 

        }
     
            $shape_arr = array('round','cushion', 'oval', 'princess', 'emerald', 'radiant', 'pear', 'asscher', 'marquise', 'heart');

              foreach ($shape_arr as $val) {
                  $attr_with_shape = 'attr_'.$val.'_compatible';
                  if($product_data[$attr_with_shape]=='Yes')  {
                      update_post_meta($post_id, $attr_with_shape, 'Yes');


                     $carat_min = 'attr_'.$val.'_carat_min';
                     if($product_data[$carat_min])
                      update_post_meta($post_id, $carat_min, $product_data[$carat_min]);

                     $carat_max = 'attr_'.$val.'_carat_max';
                     if($product_data[$carat_max])
                      update_post_meta($post_id, $carat_max, $product_data[$carat_max]); 


                     $whitegold_platinum_default_img = 'attr_whitegold_platinum_'.$val.'_default_img';
                     if($product_data[$whitegold_platinum_default_img])
                      update_post_meta($post_id, $whitegold_platinum_default_img, $product_data[$whitegold_platinum_default_img]); 


                    $whitegold_platinum_img = 'attr_whitegold_platinum_'.$val.'_img';
                     if($product_data[$whitegold_platinum_img])
                      update_post_meta($post_id, $whitegold_platinum_img, $product_data[$whitegold_platinum_img]); 


                    $attr_rosegold_default_img = 'attr_rosegold_'.$val.'_default_img';
                     if($product_data[$attr_rosegold_default_img])
                      update_post_meta($post_id, $attr_rosegold_default_img, $product_data[$attr_rosegold_default_img]); 

                    $attr_rosegold_img = 'attr_rosegold_'.$val.'_img';
                     if($product_data[$attr_rosegold_img])
                      update_post_meta($post_id, $attr_rosegold_img, $product_data[$attr_rosegold_img]);

                     $attr_yellowgold_default_img = 'attr_yellowgold_'.$val.'_default_img';
                     if($product_data[$attr_yellowgold_default_img])
                      update_post_meta($post_id, $attr_yellowgold_default_img, $product_data[$attr_yellowgold_default_img]); 

                    $attr_yellowgold_img = 'attr_yellowgold_'.$val.'_img';
                     if($product_data[$attr_yellowgold_img])
                      update_post_meta($post_id, $attr_yellowgold_img, $product_data[$attr_yellowgold_img]);
                      

                  } else 
                      update_post_meta($variation_post_id, $attr_with_shape, 'No');             
                    
              }

           



        

     


     
}



function insert_product_attributes ($post_id, $available_attributes, $variations)  
{

  

    foreach ($available_attributes as $attribute) // Go through each attribute
    {   
        $values = array(); // Set up an array to store the current attributes values.

        foreach ($variations as $variation) // Loop each variation in the file
        {
            $attribute_keys = array_keys($variation['attributes']); // Get the keys for the current variations attributes

            foreach ($attribute_keys as $key) // Loop through each key
            {
                if ($key === $attribute) // If this attributes key is the top level attribute add the value to the $values array
                {
                    $values[] = $variation['attributes'][$key];
                }
            }
        }

        // Essentially we want to end up with something like this for each attribute:
        // $values would contain: array('small', 'medium', 'medium', 'large');

        $values = array_unique($values); // Filter out duplicate values

        // Store the values to the attribute on the new post, for example without variables:
        // wp_set_object_terms(23, array('small', 'medium', 'large'), 'pa_size');
        wp_set_object_terms($post_id, $values, 'pa_' . $attribute);
    }

    $product_attributes_data = array(); // Setup array to hold our product attributes data

    foreach ($available_attributes as $attribute) // Loop round each attribute
    {
        $product_attributes_data['pa_'.$attribute] = array( // Set this attributes array to a key to using the prefix 'pa'

            'name'         => 'pa_'.$attribute,
            'value'        => '',
            'is_visible'   => '1',
            'is_variation' => '1',
            'is_taxonomy'  => '1'

        );
    }

    update_post_meta($post_id, '_product_attributes', $product_attributes_data); // Attach the above array to the new posts meta data key '_product_attributes'
}


function insert_product_variations ($product,$post_id, $variations)  
{
    foreach ($variations as $index => $variation)
    {
        $variation_post = array( // Setup the post data for the variation

            'post_title'  => 'Variation #'.$index.' of '.count($variations).' for product#'. $post_id,
            'post_name'   => 'product-'.$post_id.'-variation-'.$index,
            'post_status' => 'publish',
            'post_parent' => $post_id,
            'post_type'   => 'product_variation',
            'guid'        => home_url() . '/?product_variation=product-' . $post_id . '-variation-' . $index
        );

        $variation_post_id = wp_insert_post($variation_post); // Insert the variation

      
        
        return  $variation_post_id; 
      

    }
}





       
  function getImage($product, $postId,$thumb_url,$imageDescription){
        // add these to work add image function


        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $tmp = download_url($thumb_url);
        preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $thumb_url, $matches);
        $file_array['name'] = basename($matches[0]);
        $file_array['tmp_name'] = $tmp;
        // If error storing temporarily, unlink
        $logtxt = '';
        if (is_wp_error($tmp)) {
        @unlink($file_array['tmp_name']);
        $file_array['tmp_name'] = '';
        return;
        }else{
        $logtxt .= "download_url: $tmp\n";
        }

        //use media_handle_sideload to upload img:
        $thumbid = media_handle_sideload( $file_array, $postId, $imageName ); //'gallery desc'


        // If error storing permanently, unlink
        if (is_wp_error($thumbid)) {
        @unlink($file_array['tmp_name']);
        $thumbid = (string)$thumbid;
        $logtxt .= "Error: media_handle_sideload error - $thumbid\n";
        }else{
        $logtxt .= "ThumbID: $thumbid\n";
        }
        set_post_thumbnail($postId, $thumbid);
        update_post_meta($postId,'variation_image_gallery', $thumbid);
        $gallery = array($thumbid);
        $product->set_gallery_image_ids($gallery);
}


  function create_category($post_id,$categories){
    if($categories){
  
        $categoryID = array();
      
        $category_arr = explode(',',$categories);
        foreach($category_arr as $value) {
             $category_arr = explode('>',$value);
            if(count($category_arr)>1){
                foreach ($category_arr as $value) {
                    $term = term_exists( $value, 'product_cat' );
                    if ( $term !== 0 && $term !== null ) {
                        $term = get_term_by('name', $value, 'product_cat');
                        $categoryID[] = $term->term_id;
                    } else {

                                    $term = term_exists($category_arr[0], 'product_cat' );
                                    if ( $term !== 0 && $term !== null ) {
                                        $term = get_term_by('name', $category_arr[0], 'product_cat');
                                        $categoryID[] = $term->term_id;
                                    } else {
                                    // replace non letter or digits by -
                                      $cat_name = preg_replace('~[^\pL\d]+~u', '-', $category_arr[0]);

                                      // transliterate
                                      $cat_name = iconv('utf-8', 'us-ascii//TRANSLIT', $cat_name);

                                      // remove unwanted characters
                                      $cat_name = preg_replace('~[^-\w]+~', '', $cat_name);

                                      // trim
                                      $cat_name = trim($cat_name, '-');

                                       // remove space
                                      $cat_name = str_replace(' ', '-', $cat_name);

                                      // remove duplicate -
                                      $cat_name = preg_replace('~-+~', '-', $cat_name);

                                      // lowercase
                                      $cat_name = strtolower($cat_name);

                                    $parent = wp_insert_term(
                                        $category_arr[0], // category name
                                        'product_cat', // taxonomy
                                        array(                                            
                                            'slug' => $cat_name, // optional
                                        )
                                    );
                                    
                                     $categoryID[] = $parent['term_id'];

                                    }

                                     $term = term_exists($category_arr[1], 'product_cat' );
                                    if ( $term !== 0 && $term !== null ) {
                                        $term = get_term_by('name', $category_arr[1], 'product_cat');
                                        $categoryID[] = $term->term_id;
                                    } else {

                                     // replace non letter or digits by -
                                      $cat_name1 = preg_replace('~[^\pL\d]+~u', '-', $category_arr[1]);

                                      // transliterate
                                      $cat_name1 = iconv('utf-8', 'us-ascii//TRANSLIT', $cat_name1);

                                      // remove unwanted characters
                                      $cat_name1 = preg_replace('~[^-\w]+~', '', $cat_name1);

                                      // trim
                                      $cat_name1 = trim($cat_name1, '-');

                                       // remove space
                                      $cat_name1 = str_replace(' ', '-', $cat_name1);

                                      // remove duplicate -
                                      $cat_name1 = preg_replace('~-+~', '-', $cat_name1);

                                      // lowercase
                                      $cat_name1 = strtolower($cat_name1);

                                      $child = wp_insert_term(
                                            $category_arr[1], // category name
                                            'product_cat', // taxonomy
                                            array(
                                              
                                                'slug' => $cat_name1, // optional
                                                'parent' => $parent['term_id'], // set it as a sub-category
                                            )
                                        );
                                      
                                      $categoryID[] = $child['term_id'];
                              }

                    }

                }
            } else{

                                $term = term_exists( $value, 'product_cat' );
                                if ( $term !== 0 && $term !== null ) {
                                    $term = get_term_by('name', $value, 'product_cat');
                                    $categoryID[] = $term->term_id;
                                } else {


                                 // replace non letter or digits by -
                                      $cat_name = preg_replace('~[^\pL\d]+~u', '-', $category_arr[0]);

                                      // transliterate
                                      $cat_name = iconv('utf-8', 'us-ascii//TRANSLIT', $cat_name);

                                      // remove unwanted characters
                                      $cat_name = preg_replace('~[^-\w]+~', '', $cat_name);

                                      // trim
                                      $cat_name = trim($cat_name, '-');

                                       // remove space
                                      $cat_name = str_replace(' ', '-', $cat_name);

                                      // remove duplicate -
                                      $cat_name = preg_replace('~-+~', '-', $cat_name);

                                      // lowercase
                                      $cat_name = strtolower($cat_name);

                                        $parent = wp_insert_term(
                                            $category_arr[0], // category name
                                            'product_cat', // taxonomy
                                            array(                                            
                                                'slug' => $cat_name, // optional
                                            )
                                        );
                                }        
                                     if($parent['term_id'])   
                                      $categoryID[] = $parent['term_id'];   

            }
            


        }

        wp_set_object_terms($post_id,  array_unique($categoryID), 'product_cat'); // Set up its categories
    }

   
  }      

?>

<?php get_footer(); ?>