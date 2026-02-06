<?php
$categories = get_terms(['taxonomy' => 'gp_menu_category', 'hide_empty' => false]);
?>
<div class="gourmetpress-menu">
    <div class="gp-categories">
        <?php foreach ($categories as $cat): ?>
            <button class="gp-cat-btn" data-cat="<?php echo esc_attr($cat->slug); ?>">
                <?php echo esc_html($cat->name); ?>
            </button>
        <?php endforeach; ?>
    </div>
    
    <div class="gp-items">
        <?php 
        $items = get_posts(['post_type' => 'gp_menu_item', 'posts_per_page' => -1]);
        foreach ($items as $item): 
            $price = get_post_meta($item->ID, '_price', true);
            $cats = get_the_terms($item->ID, 'gp_menu_category');
            $cat_slugs = $cats ? wp_list_pluck($cats, 'slug') : [];
        ?>
            <div class="gp-item" data-categories="<?php echo esc_attr(implode(',', $cat_slugs)); ?>">
                <?php if (has_post_thumbnail($item->ID)): ?>
                    <?php echo get_the_post_thumbnail($item->ID, 'medium'); ?>
                <?php endif; ?>
                <h3><?php echo esc_html($item->post_title); ?></h3>
                <p><?php echo wp_trim_words($item->post_content, 20); ?></p>
                <div class="gp-price">$<?php echo number_format($price, 2); ?></div>
                <button class="gp-add-to-cart" data-id="<?php echo $item->ID; ?>" data-name="<?php echo esc_attr($item->post_title); ?>" data-price="<?php echo $price; ?>">
                    Add to Cart
                </button>
            </div>
        <?php endforeach; ?>
    </div>
</div>
