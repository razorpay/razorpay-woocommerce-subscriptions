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
        $this->prepare_items();

        $this->views();

        echo '<form method="post">
            <input type="hidden" name="page" value="razorpay_subscriptions">';

        $this->display();

        echo '</form></div>
            </div>';
    }

    /**
     * Add columns to grid view
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
                return $item[ $column_name ];

            default:

                return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
        }
    }

    /**
     * Prepare admin view
     */
    function prepare_items()
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

        $subscription_pages = $this->get_items();

        $columns = $this->get_columns();
        $hidden = array();
        $this->_column_headers = array($columns, $hidden);

        $count = count($subscription_pages);
        $this->items = $subscription_pages;

        // Set the pagination
        $this->set_pagination_args( array(
            'total_items' => $count,
            'per_page'    => $per_page,
            'total_pages' => ceil( $count / $per_page )
        ) );
    }

    function get_items()
    {
        $items = array();

        $razorpay = new WC_Razorpay();

        $api = $razorpay->getRazorpayApiInstance();

        try
        {
            $subscriptions = $api->subscription->all();
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
                    'created_at' => date("d M, Y", $subscription['created_at']),
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
        $this->items = $plans_page;

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
            $plans = $api->plan->all();
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
                $items[] = array(
                    'plan_id' => $plan['id'],
                    'plan_name' => $plan['item']['name'],
                    'billing_cycle' => $plan['period'],
                    'amount' => '<span>'.get_woocommerce_currency_symbol($plan['item']['currency']) .'</span>'. (int)($plan['item']['amount'] / 100),
                    'created_at' => date("d M, Y", $plan['created_at']),
                );
            }
        }
        return $items;
    }

    /**
     * Add columns to grid view
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

    function subscription_header()
    {

        ?>
        <header id="subscription-header" class="subscription-header">
            <a <?php if ($_GET['page'] == "razorpay_subscriptions") { ?> class="active" <?php } ?>
                href="?page=razorpay_subscriptions">Subscriptions</a>
            <a <?php if ($_GET['page'] == "razorpay_subscription_plans") { ?> class="active" <?php } ?>
                href="?page=razorpay_subscription_plans">Plans</a>
        </header>
        <?php
    }

}