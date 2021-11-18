<?php

if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class RZP_Subscription_List extends WP_List_Table {

    function __construct()
    {
        parent::__construct(
            array(
                'singular'  => 'wp_list_text_link', //Singular label
                'plural'    => 'wp_list_test_links', //plural label, also this well be one of the table css class
                'ajax'      => false        //does this table support ajax?
            )
        );
    }

    function razorpay_subscriptions()
    {
        echo '<div>
            <div class="wrap">';

        $this->subscription_header();

        $this->prepare_subscription_items();

        $this->views();

        echo '<form method="get">
            <input type="hidden" name="page" value="razorpay_subscriptions">';

        ?>
        <p class="search-box">
            <label class="" for="search_id-search-input">Status </label>
            <select name="status" id="search_id-search-input">
                <option value=""></option>
                <option value="created" <?php if (isset($_GET['status']) && $_GET['status'] == "created") { ?> selected <?php } ?> >
                    Created
                </option>
                <option value="authenticated" <?php if (isset($_GET['status']) && $_GET['status'] == "authenticated") { ?> selected <?php } ?> >
                    Authenticated
                </option>
                <option value="active" <?php if (isset($_GET['status']) && $_GET['status'] == "active") { ?> selected <?php } ?>>
                    Active
                </option>
                <option value="pending" <?php if (isset($_GET['status']) && $_GET['status'] == "pending") { ?> selected <?php } ?> >
                    Pending
                </option>
                <option value="paused" <?php if (isset($_GET['status']) && $_GET['status'] == "paused") { ?> selected <?php } ?> >
                    Paused
                </option>
                <option value="halted" <?php if (isset($_GET['status']) && $_GET['status'] == "halted") { ?> selected <?php } ?> >
                    Halted
                </option>
                <option value="cancelled" <?php if (isset($_GET['status']) && $_GET['status'] == "cancelled") { ?> selected <?php } ?> >
                    Cancelled
                </option>
                <option value="completed" <?php if (isset($_GET['status']) && $_GET['status'] == "completed") { ?> selected <?php } ?> >
                    Completed
                </option>
                <option value="expired" <?php if (isset($_GET['status']) && $_GET['status'] == "expired") { ?> selected <?php } ?> >
                    Expired
                </option>
            </select>
            <input type="submit" id="search-submit" class="button" value="search">
            <a href="<?php echo admin_url('admin.php?page=razorpay_subscriptions'); ?>">Clear</a></p>

        <?php
        $this->display();

        echo '</form></div>
            </div>';
    }

    /**
     * Add subscription columns to grid view
     */
    function get_columns()
    {

        $columns = array(
            'subscription_id'=>__('Subscription Id'),
            'plan_id'=>__('Plan Id'),
            'subscription_link'=>__('Subscription Link'),
            'customer_id'=>__('Customer Id'),
            'next_due'=>__('Next Due on'),
            'created_at'=>__('Created At'),
            'status'=>__('Status'),
        );

        return $columns;
    }

    function column_default( $item, $column_name )
    {
        switch($column_name)
        {
            case 'subscription_id':
            case 'plan_id':
            case 'subscription_link':
            case 'customer_id':
            case 'next_due':
            case 'created_at':
            case 'status':
            case 'plan_name':
            case 'billing_cycle':
            case 'amount':
            case 'addon_id':
            case 'addon_name':
            case 'addon_desciption':
            case 'addon_applied_to':
            case 'invoice_id':
                return $item[ $column_name ];

            default:

                return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
        }
    }

    /**
     * Prepare admin view
     */
    function prepare_subscription_items()
    {

        $per_page = 10;
        $current_page = $this->get_pagenum();

        if (1 < $current_page)
        {
            $offset = $per_page * ( $current_page - 1 );
        }
        else
        {
            $offset = 0;
        }

        $status = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';

        $subscription_page = $this->get_subscription_items($status);

        $columns = $this->get_columns();
        $hidden = array();
        $this->_column_headers = array($columns, $hidden);

        $count = count($subscription_page);

        $subscription_pages = array();
        for ($i = 0; $i < $count; $i++) {
            if ($i >= $offset && $i < $offset + $per_page) {
                $subscription_pages[] = $subscription_page[$i];
            }
        }

        $this->items = $subscription_pages;

        // Set the pagination
        $this->set_pagination_args( array(
            'total_items' => $count,
            'per_page'    => $per_page,
            'total_pages' => ceil( $count / $per_page )
        ) );
    }

    function get_subscription_items($status)
    {
        $items = array();

        $razorpay = new WC_Razorpay();

        $api = $razorpay->getRazorpayApiInstance();

        try
        {
            $subscriptions = $api->subscription->all(['status'=>$status, 'count'=>100]);
        }
        catch (Exception $e)
        {
            $message = $e->getMessage();

            wp_die('<div class="error notice">
                    <p>RAZORPAY ERROR: Subscription fetch failed with the following message: '.$message.'</p>
                 </div>');
        }
        if ($subscriptions)
        {
            foreach ($subscriptions['items'] as $subscription)
            {
                $items[] = array(
                    'subscription_id' => $subscription['id'],
                    'plan_id' => $subscription['plan_id'],
                    'subscription_link' => $subscription['short_url'],
                    'customer_id' => $subscription['customer_id'],
                    'next_due' => (!empty($subscription['charge_at'])) ? date("d F Y", $subscription['charge_at']) : '--',
                    'created_at' => date("d F, Y", $subscription['created_at']),
                    'status' => ucfirst($subscription['status']),
                );
            }
        }
        return $items;
    }

    function razorpay_subscription_plans()
    {
        echo '<div>
            <div class="wrap">';

        $this->subscription_header();
        $this->prepare_plan_items();

        $this->views();

        echo '<form method="post">
            <input type="hidden" name="page" value="razorpay_subscription_plans">';

        $this->display();

        echo '</form></div>
            </div>';
    }

    function prepare_plan_items()
    {

        $per_page = 10;
        $current_page = $this->get_pagenum();

        if (1 < $current_page)
        {
            $offset = $per_page * ( $current_page - 1 );
        }
        else
        {
            $offset = 0;
        }

        $plans_page = $this->get_plan_items();

        $columns = $this->get_plans_columns();
        $hidden = array();
        $this->_column_headers = array($columns, $hidden);

        $count = count($plans_page);

        $plans_pages = array();
        for ($i = 0; $i < $count; $i++) {
            if ($i >= $offset && $i < $offset + $per_page) {
                $plans_pages[] = $plans_page[$i];
            }
        }
        $this->items = $plans_pages;

        // Set the pagination
        $this->set_pagination_args( array(
            'total_items' => $count,
            'per_page'    => $per_page,
            'total_pages' => ceil( $count / $per_page )
        ) );
    }

    function get_plan_items()
    {
        $items = array();

        $razorpay = new WC_Razorpay();

        $api = $razorpay->getRazorpayApiInstance();

        try
        {
            $plans = $api->plan->all(['count'=>100]);
        }
        catch (Exception $e)
        {
            $message = $e->getMessage();

            wp_die('<div class="error notice">
                    <p>RAZORPAY ERROR: Subscription Plans fetch failed with the following message: '.$message.'</p>
                 </div>');
        }
        if ($plans)
        {
            foreach ($plans['items'] as $plan)
            {
                $interval = ($plan['interval']== 1)? '': $plan['interval'];
                $period = ($plan['interval'] > 1)? ucfirst(rtrim($plan['period'], "ly").'s'): ucfirst(rtrim($plan['period'], "ly"));
                $items[] = array(
                    'plan_id' => $plan['id'],
                    'plan_name' => $plan['item']['name'],
                    'billing_cycle' => 'Every '.$interval.' '.$period,
                    'amount' => '<span>'.get_woocommerce_currency_symbol($plan['item']['currency']) .'</span>'. (int)($plan['item']['amount'] / 100),
                    'created_at' => date("d F, Y", $plan['created_at']),
                );
            }
        }
        return $items;
    }

    /**
     * Add plan columns to grid view
     */
    function get_plans_columns()
    {

        $columns = array(
            'plan_id'=>__('Plan Id'),
            'plan_name'=>__('Plan Name'),
            'billing_cycle'=>__('Billing Cycle'),
            'amount'=>__('Amount'),
            'created_at'=>__('Created At'),
        );

        return $columns;
    }

    function razorpay_subscription_addons()
    {
        echo '<div>
            <div class="wrap">';

        $this->subscription_header();
        $this->prepare_addon_items();

        $this->views();
        echo '<p>The add-ons here are the signup fees charged for subscription products.</p>';
        echo '<form method="post">
            <input type="hidden" name="page" value="razorpay_subscription_addons">';

        $this->display();

        echo '</form></div>
            </div>';
    }

    function prepare_addon_items()
    {

        $per_page = 10;
        $current_page = $this->get_pagenum();

        if (1 < $current_page)
        {
            $offset = $per_page * ( $current_page - 1 );
        }
        else
        {
            $offset = 0;
        }

        $addon_page = $this->get_addon_items();

        $columns = $this->get_addon_columns();
        $hidden = array();
        $this->_column_headers = array($columns, $hidden);

        $count = count($addon_page);

        $addon_pages = array();
        for ($i = 0; $i < $count; $i++) {
            if ($i >= $offset && $i < $offset + $per_page) {
                $addon_pages[] = $addon_page[$i];
            }
        }
        $this->items = $addon_pages;

        // Set the pagination
        $this->set_pagination_args( array(
            'total_items' => $count,
            'per_page'    => $per_page,
            'total_pages' => ceil( $count / $per_page )
        ) );
    }

    function get_addon_items()
    {
        $items = array();

        $razorpay = new WC_Razorpay();

        $api = $razorpay->getRazorpayApiInstance();

        try
        {
            $addons = $api->addon->fetchAll(['count'=>100]);
        }
        catch (Exception $e)
        {
            $message = $e->getMessage();

            wp_die('<div class="error notice">
                    <p>RAZORPAY ERROR: Add-Ons fetch failed with the following message: '.$message.'</p>
                 </div>');
        }
        if ($addons)
        {
            foreach ($addons['items'] as $addon)
            {
                $items[] = array(
                    'addon_id' => $addon['id'],
                    'addon_name' => $addon['item']['name'],
                    'addon_desciption' => (!empty($addon['item']['description'])) ? $addon['item']['description'] : '--',
                    'addon_applied_to' => (!empty($addon['subscription_id'])) ? $addon['subscription_id'] : '--',
                    'amount' => '<span>'.get_woocommerce_currency_symbol($addon['item']['currency']) .'</span>'. (int)($addon['item']['amount'] / 100),
                    'invoice_id' => (!empty($addon['invoice_id'])) ? $addon['invoice_id'] : '--',
                    'created_at' => date("d F, Y", $addon['created_at']),
                );
            }
        }
        return $items;
    }

    /**
     * Add Addon columns to grid view
     */
    function get_addon_columns()
    {

        $columns = array(
            'addon_id'=>__('Add-on Id'),
            'addon_name'=>__('Add-on Name'),
            'addon_desciption'=>__('Add-on Description'),
            'addon_applied_to'=>__('Applied to'),
            'amount'=>__('Amount'),
            'invoice_id'=>__('Invoice Id'),
            'created_at'=>__('Created At'),
        );

        return $columns;
    }

    function subscription_header()
    {

        ?>
        <header id="subscription-header" class="subscription-header">
            <a <?php if ($_GET['page'] == "razorpay_subscriptions") { ?> class="active" <?php } ?>
                href="?page=razorpay_subscriptions">Subscriptions</a>
            <a <?php if ($_GET['page'] == "razorpay_subscription_plans") { ?> class="active" <?php } ?>
                href="?page=razorpay_subscription_plans">Plans</a>
            <a <?php if ($_GET['page'] == "razorpay_subscription_addons") { ?> class="active" <?php } ?>
                    href="?page=razorpay_subscription_addons">Add-Ons</a>
        </header>
        <?php
    }

}
