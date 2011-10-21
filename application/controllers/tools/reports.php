<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Reports extends App_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('customer_model');
		$this->load->model('module_model');
		$this->load->model('user_model');
		$this->load->model('billing_model');
		$this->load->model('reports_model');
	}		


	/*
	 * ------------------------------------------------------------------------
	 *  sends customer summary to view or summary file for download
	 *  input: style (view|file)
	 *  input: organization id (for file generated), optional
	 * ------------------------------------------------------------------------
	 */
	function summary($style, $organization_id = NULL)
	{
		// check if the user has manager privileges first
		$myresult = $this->user_model->user_privileges($this->user);

		if ($myresult['manager'] == 'n') 
		{
			echo lang('youmusthaveadmin')."<br>";
			exit; 
		}

		// load settings and general
		$this->load->model('settings_model');
		$this->load->model('general_model');

		// set org id input or default to org id 1 if none specified
		if ($organization_id)
		{
			$dataview['organization_id'] = $organization_id;
		} 
		else
		{
			if (!$this->input->post('organization_id'))
			{
				$dataview['organization_id'] = 1;
				$organization_id = 1;
			}
			else
			{
				$dataview['organization_id'] = $this->input->post('organization_id');
				$organization_id = $this->input->post('organization_id');
			}
		}

		if ($style == 'file')
		{
			// load the download helper
			$this->load->helper('download');

			$filename = "summary.csv";

			$datafile = lang('services').",Frequency,Category,Customers,Service Cost,".
				"Monthly Total\n";
		}

		// initialize the count of paid monthly services
		$paidsubscriptions = 0;
		$count_creditcard = 0;
		$count_invoice = 0;
		$count_einvoice = 0;
		$count_prepay = 0;
		$count_prepaycc = 0;
		$total_customers = 0;
		$total_service_cost = 0;
		$total_monthly = 0;

		$services_by_org = $this->reports_model->services_by_org($organization_id);

		// initialize arrays to keep our results in
		// make hash/array of master service id and amount being charged
		// and another array to count how many of customers have that type of charge
		$price_array = array();
		$count_array = array();

		$i = 0; // count the billing services
		foreach($services_by_org AS $myresult)
		{
			$billing_id = $myresult['u_bid'];
			$user_services_id = $myresult['u_id'];
			$pricerate = $myresult['m_pricerate'];
			$usage_multiple = $myresult['u_usage'];
			$master_service_id = $myresult['m_id'];

			$billed_amount = ($pricerate*$usage_multiple);

			// round the tax to two decimal places
			$billed_amount = sprintf("%.2f", $billed_amount);

			// Insert results into an array
			if (isset($price_array[$master_service_id])) 
			{
				$price_array[$master_service_id] = 
					$price_array[$master_service_id] + $billed_amount;
				$count_array[$master_service_id]++;
			} 
			else 
			{
				$price_array[$master_service_id] = $billed_amount;
				$count_array[$master_service_id] = 1;	    
			}    

		} // end while


		// print each item in the price and count arrays
		foreach($price_array as $master_service_id_value => $total_billed) 
		{
			$servicearray = $this->reports_model->master_service_info($master_service_id_value);

			// count the number of taxes
			$count = $count_array[$master_service_id_value];

			// initialize dataview service listing
			$dataview['service_listing'] = '';

			foreach ($servicearray AS $myserviceresult) 
			{
				$service_description = $myserviceresult['service_description'];
				$rate = $myserviceresult['pricerate'];
				$frequency = $myserviceresult['frequency'];
				$category = $myserviceresult['category'];

				// add to the displayed paid subscription count total, 
				// do not count free or on time services as a subscription
				if (($rate > 0) AND ($frequency > 0)) 
				{
					$paidsubscriptions = $paidsubscriptions + $count;
				}       

				// if frequency is greater than 1 divide the total amount by the frequency
				if ($frequency > 1) 
				{
					$total_billed = $total_billed/$frequency;
				}

				if ($style == 'view')
				{
					$dataview['service_listing'] .= "<td>$service_description</td>".
						"<td>$frequency</td>".
						"<td>$category</td><td>$count</td><td>$rate</td>".
						"<td>$total_billed</td><tr>";
				}
				else
				{
					$datafile .= "$service_description,$frequency,$category,$count,".
						"$rate,$total_billed\n";
				}

				// add totals
				$total_customers = sprintf("%.2f",$total_customers + $count);
				$total_service_cost = sprintf("%.2f",$total_service_cost + $rate);
				$total_monthly = sprintf("%.2f",$total_monthly + $total_billed);
			}
		}


		/*--------------------------------------------------------------------------*/
		// calculate taxes for all taxed services at this time
		// this part may take a long time
		/*--------------------------------------------------------------------------*/

		// initialize arrays to keep our results in
		// make hash/array of tax rate id and number of customers being charged that tax rate
		// and another array to count how many of those taxes are charged
		$tax_array = array();
		$count_array = array();
		
		$taxresults = $this->reports_model->taxes_by_org($organization_id);

		// count the number of taxes
		$i = 0;

		foreach ($taxresults AS $mytaxresult) 
		{
			$billing_id = $mytaxresult['b_id'];
			$taxed_services_id = $mytaxresult['ts_id'];
			$user_services_id = $mytaxresult['us_id'];
			$service_freq = $mytaxresult['ms_freq'];
			$billing_freq = $mytaxresult['t_freq'];	
			$if_field = $mytaxresult['tr_if_field'];
			$if_value = $mytaxresult['tr_if_value'];
			$percentage_or_fixed = $mytaxresult['tr_percentage_or_fixed'];
			$my_account_number = $mytaxresult['us_account_number'];
			$usage_multiple = $mytaxresult['us_usage_multiple'];
			$pricerate = $mytaxresult['ms_pricerate'];
			$taxrate = $mytaxresult['tr_rate'];
			$tax_rate_id = $mytaxresult['tr_id'];
			$tax_exempt_rate_id = $mytaxresult['te_tax_rate_id'];

			// check that they are not exempt
			if ($tax_exempt_rate_id <> $tax_rate_id) 
			{
				// check the if_field before adding to see if 
				// the tax applies to this customer
				if ($if_field <> '') 
				{
					$checkvalue = $this->customer_model->check_if_field($if_field, $my_account_number);
				} 
				else 
				{
					$checkvalue = TRUE;
					$if_value = TRUE;
				}

				// check for any case, so lower them here
				$checkvalue = strtolower($checkvalue);
				$if_value = strtolower($if_value);

				if (($checkvalue == $if_value) AND ($billing_freq > 0)) 
				{
					if ($percentage_or_fixed == 'percentage') 
					{
						if ($service_freq > 0) 
						{
							$servicecost = sprintf("%.2f",$taxrate * $pricerate);
							// removed freq from this calculation since it is just for a monthly snapshot
							$tax_amount = sprintf("%.2f",$servicecost * $usage_multiple); 
						} 
						else 
						{
							$servicecost = $pricerate * $usage_multiple;
							$tax_amount = $taxrate * $servicecost;
						}
					} 
					else 
					{
						// fixed fee amount does not depend on price or usage
						$tax_amount = $taxrate;
					}

					// round the tax to two decimal places
					$tax_amount = sprintf("%.2f", $tax_amount);

					// Insert results into an array

					if (isset($tax_array[$taxed_services_id])) 
					{
						$tax_array[$taxed_services_id] = $tax_array[$taxed_services_id] + $tax_amount;
						$count_array[$taxed_services_id]++;
					} 
					else 
					{
						$tax_array[$taxed_services_id] = $tax_amount;
						$count_array[$taxed_services_id] = 1;	    
					}

				} //endif if_field/billing_freq
			} // endif exempt
		}

		// initialize dataview tax listing
		$dataview['tax_listing'] = '';

		// print each item in the tax and count arrays
		foreach($tax_array as $taxed_services_id_value => $total_taxed) 
		{
			$taxresults = $this->reports_model->taxed_services($taxed_services_id_value);

			// count the number of taxes
			$count = $count_array[$taxed_services_id_value];

			foreach ($taxresults AS $mytaxresult) 
			{
				$description = $mytaxresult['description'];
				$service_description = $mytaxresult['service_description'];
				$rate = $mytaxresult['rate'];
				$category = $mytaxresult['category'];

				if ($style == 'view')
				{
					$dataview['tax_listing'] .= "<td>$description for $service_description</td>".
						"<td></td>".
						"<td>$category</td><td>$count</td>".
						"<td>$rate</td><td>$total_taxed</td><tr>";
				}
				else
				{
					$datafile .= "$description for $service_description,,$category,".
						"$count,$rate,$total_taxed\n";
				}

				// add totals
				$total_customers = sprintf("%.2f",$total_customers + $count);
				$total_service_cost = sprintf("%.2f",$total_service_cost + $rate);
				$total_monthly = sprintf("%.2f",$total_monthly + $total_taxed);

			}
		}


		if ($style == 'view')
		{
			// print the table footer
			$dataview['listing_footer'] = "<td style=\"border-top: 1px solid black; font-weight: bold;\">".
				lang('total').":</td> ".
				"<td style=\"border-top: 1px solid black; font-weight: bold;\">&nbsp;</td> ".
				"<td style=\"border-top: 1px solid black; font-weight: bold;\">&nbsp;</td> ".
				"<td style=\"border-top: 1px solid black; font-weight: bold;\">$total_customers</td> ".
				"<td style=\"border-top: 1px solid black; font-weight: bold;\">$total_service_cost</td> ".
				"<td style=\"border-top: 1px solid black; font-weight: bold;\">$total_monthly</td><tr>";
		}
		else
		{
			$datafile .= ",,,$total_customers,$total_service_cost,$total_monthly\n";
		}

		$dataview['paidsubscriptions'] = $paidsubscriptions;

		// initialize billing methods dataview
		$dataview['billing_methods'] = '';
		
		$results = $this->reports_model->total_services($organization_id);

		foreach ($results AS $myresult) 
		{
			$count = $myresult['TotalNumber'];
			$billingmethod = $myresult['bt_method'];
			$dataview['billing_methods'] .= "$billingmethod: $count<br>\n";	
		}

		// initialize data view service_categories
		$dataview['service_categories'] = '';

		$results = $this->reports_model->services_in_categories($organization_id);
		
		foreach ($results as $myresult) 
		{
			$count = $myresult['TotalNumber'];
			$category = $myresult['m_category'];
			$dataview['service_categories'] .= "$category: $count<br>\n";	
		}
		echo "</blockquote>";

		$dataview['totalcustomers'] = $this->reports_model->number_of_customers();

		$dataview['totalpayingcustomers'] = $this->reports_model->number_of_non_free_customers();

		if ($style == 'view')
		{
			// load the header without the sidebar to get the stylesheet in there
			$this->load->view('header_no_sidebar_view');

			$dataview['orglist'] = $this->general_model->list_organizations();
			$this->load->view('tools/reports/summary_view', $dataview);
		}
		else
		{
			// file style
			force_download($filename, $datafile);
		}
	}


	/*
	 * ------------------------------------------------------------------------
	 *  show the revenue report
	 * ------------------------------------------------------------------------
	 */
	function revenue()
	{
		// load settings and general
		$this->load->model('settings_model');
		$this->load->model('general_model');

		// make sure they have manager privileges
		$myresult = $this->user_model->user_privileges($this->user);
		if ($myresult['manager'] == 'n') 
		{
			echo lang('youmusthaveadmin')."<br>";
			exit; 
		}
		
		$empty_day_1  = date("Y-m-d", mktime(0, 0, 0, date("m")-1  , date("d"), date("Y")));
		$empty_day_2  = date("Y-m-d", mktime(0, 0, 0, date("m")  , date("d"), date("Y")));

		$day1 = $this->input->post('day1');
		$day2 = $this->input->post('day2');
		$org_id = $this->input->post('organization_id');

		// if nothing was entered for the day, then put in the defaults month period
		if ($day1 =='') { $day1 = $empty_day_1; }
		if ($day2 =='') { $day2 = $empty_day_2; }

		$data['day1'] = $day1;
		$data['day2'] = $day2;

		$data['orglist'] = $this->general_model->list_organizations();
		$data['servicerevenue'] = $this->reports_model->servicerevenue($day1, $day2, $org_id);
		$data['creditrevenue'] = $this->reports_model->creditrevenue($day1, $day2, $org_id);
		$data['refundrevenue'] = $this->reports_model->refundrevenue($day1, $day2, $org_id);
		$data['discountrevenue'] = $this->reports_model->discountrevenue($day1, $day2, $org_id);
		$data['taxrevenue'] = $this->reports_model->taxrevenue($day1, $day2);
		$data['taxrefunds'] = $this->reports_model->taxrefunds($day1, $day2);

		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		$this->load->view('tools/reports/revenue_view', $data);
	}

	function refunds()
	{
		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		$this->load->view('tools/reports/refunds_view');
	}


	function pastdue()
	{
		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		$this->load->view('tools/reports/pastdue_view');
	}


	function paymentstatus()
	{
		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		$this->load->view('tools/reports/paymentstatus_view');
	}


	function services()
	{
		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		$this->load->view('tools/reports/services_view');
	}


	function sources()
	{
		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		$this->load->view('tools/reports/sources_view');
	}


	function exempt()
	{
		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		$this->load->view('tools/reports/exempt_view');
	}


	function printnotices()
	{
		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		$this->load->view('tools/reports/printnotices_view');
	}


	function servicechurn()
	{
		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		$this->load->view('tools/reports/servicechurn_view');
	}

}

/* End of file reports */
/* Location: ./application/controllers/tools/reports.php */
