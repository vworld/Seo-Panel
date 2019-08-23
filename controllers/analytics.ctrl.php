<?php

/***************************************************************************
 *   Copyright (C) 2009-2011 by Geo Varghese(www.seopanel.in)  	   *
 *   sendtogeo@gmail.com   												   *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU General Public License as published by  *
 *   the Free Software Foundation; either version 2 of the License, or     *
 *   (at your option) any later version.                                   *
 *                                                                         *
 *   This program is distributed in the hope that it will be useful,       *
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of        *
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         *
 *   GNU General Public License for more details.                          *
 *                                                                         *
 *   You should have received a copy of the GNU General Public License     *
 *   along with this program; if not, write to the                         *
 *   Free Software Foundation, Inc.,                                       *
 *   59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.             *
 ***************************************************************************/

// include google api module
include_once(SP_CTRLPATH . "/googleapi.ctrl.php");

// class defines all google analytics api controller functions
class AnalyticsController extends GoogleAPIController {
	
    var $spTextGA;
    var $metrics;
	var $metricList;
	var $defaultMetricName = "users";
	var $dimensionName = "source";
	
	function AnalyticsController() {
		parent::__construct();
		$this->spTextGA = $this->getLanguageTexts('analytics', $_SESSION['lang_code']);
		$this->set('spTextGA', $this->spTextGA);
		$this->metrics = array(
		    'users' => $this->spTextGA['Users'],
		    'newUsers' => $this->spTextGA['New Users'],
		    'sessions' => $this->spTextGA['Sessions'],
		    'bounceRate' => $this->spTextGA['Bounce Rate'],
		    'avgSessionDuration' => $this->spTextGA['Avg. Session Duration'],
		    'goalCompletionsAll' => $this->spTextGA['Goal Completions'],
		);
		$this->metricList = array_keys($this->metrics);
	}
	
	/*
	 * function to get analytics query result
	 */
	function getAnalyticsResults($userId, $VIEW_ID, $startDate, $endDate) {
		$result = array('status' => false);
		
		if (empty($VIEW_ID)) {
		    $result['msg'] = "Error: search query analytics - Analytics view id is not set for website";
		    return $result;
		}
		
		try {
			
    		$client = $this->getAuthClient($userId);
    		
    		// check whether client created successfully
    		if (!is_object($client)) {
    		    $result['msg'] = $client;
    		    return $result;
    		}
    		
    		$analytics = new Google_Service_AnalyticsReporting($client);
    		
    		// Create the DateRange object.
    		$dateRange = new Google_Service_AnalyticsReporting_DateRange();
    		$dateRange->setStartDate($startDate);
    		$dateRange->setEndDate($endDate);
    		
    		// Create the Metrics object list
    		$metricObjList = [];
    		foreach ($this->metricList as $metricName) {
	    		$sessions = new Google_Service_AnalyticsReporting_Metric();
	    		$sessions->setExpression("ga:$metricName");
	    		$sessions->setAlias($metricName);
	    		$metricObjList[] = $sessions;
    		}
    		
    		// Create the dimension.
    		$dimension = new Google_Service_AnalyticsReporting_Dimension();
    		$dimension->setName("ga:$this->dimensionName");
    		
    		// Create the Ordering.    		
    		$ordering = new Google_Service_AnalyticsReporting_OrderBy();
    		$ordering->setFieldName("ga:$this->defaultMetricName");
    		$ordering->setOrderType("VALUE");
    		$ordering->setSortOrder("DESCENDING");
    		
    		// Create the ReportRequest object.
    		$request = new Google_Service_AnalyticsReporting_ReportRequest();
    		$request->setViewId($VIEW_ID);
    		$request->setDateRanges($dateRange);
    		$request->setMetrics($metricObjList);
    		$request->setDimensions(array($dimension));
    		$request->setOrderBys($ordering);
    		
    		$body = new Google_Service_AnalyticsReporting_GetReportsRequest();
    		$body->setReportRequests( array( $request) );
    		$res = $analytics->reports->batchGet( $body );    		
    		$resultList = $this->formatResult($res);
    		
    		$result['status'] = true;
    		$result['resultList'] = $resultList;    		
		} catch (Exception $e) {
		    $err = $e->getMessage();
		    $result['msg'] = "Error: search query analytics - $err";
		}
		
		return $result;
		
	}	
	
	function formatResult($reports) {
		$resultList = array();
		
		// loop through the reports
		for ( $reportIndex = 0; $reportIndex < count( $reports ); $reportIndex++ ) {
			$report = $reports[ $reportIndex ];
			
			// get total value
			$totals = $report->getData()->getTotals();
			$values = $totals[0]->getValues();
			$resultList['total'] = [];
			foreach ($this->metricList as $i => $metricName) {
				$resultList['total'][$metricName] = $values[$i];
			}
			
			// get dimension type value
			$rows = $report->getData()->getRows();
			for ( $rowIndex = 0; $rowIndex < count($rows); $rowIndex++) {
				$row = $rows[$rowIndex];
				$dimensions = $row->getDimensions();
				$metrics = $row->getMetrics();
				$values = $metrics[0]->getValues();
				
				// find metric values
				$resultList[$dimensions[0]] = [];
				foreach ($this->metricList as $i => $metricName) {
					$resultList[$dimensions[0]][$metricName] = $values[$i];
				}
			}
		}
		
		return $resultList;
	}
	
	function getAnalyticsSourceList() {
	    $sourceList = [];
	    $list = $this->dbHelper->getAllRows("analytic_sources");
	    foreach ($list as $listInfo) {
	        $sourceList[$listInfo['source_name']] = $listInfo['id'];
	    }
	    
	    return $sourceList;
	}
	
	function generateSource($sourceName) {
	    $sourceId = false;
	    if ($this->dbHelper->insertRow("analytic_sources", array("source_name" => $sourceName))) {
	        $sourceId = $this->db->getMaxId("analytic_sources");
	    }
	    
	    return $sourceId;
	}
	
	/*
	 * function to store website results
	 */
	function storeWebsiteAnalytics($websiteId, $reportDate) {
		$websiteId = intval($websiteId);
		$websiteCtrler = new WebsiteController();
		$websiteInfo = $websiteCtrler->__getWebsiteInfo($websiteId);
		
		// query results from api and verify no error occured
		$result = $this->getAnalyticsResults($websiteInfo['user_id'], $websiteInfo['analytics_view_id'], $reportDate, $reportDate);
		if ($result['status']) {
		    $sourceList = $this->getAnalyticsSourceList();
				
			// loop through the result list
			foreach ($result['resultList'] as $sourceName => $reportInfo) {
			    
			    // generate source list, if not set it yet
			    if (!isset($sourceList[$sourceName])) {
			        $sourceId = $this->generateSource($sourceName);
			    } else {
			        $sourceId = $sourceList[$sourceName];
			    }
	
			    if (!empty($sourceId)) {
			        $this->insertWebsiteAnalytics($websiteId, $sourceId, $reportInfo, $reportDate);
			    } else {
			        $result['msg'] .= "Error: Analytics source id not found";
			    }
			}
		}
	
		return $result;
	
	}
	
	/*
	 * function to insert website analytics
	 */
	function insertWebsiteAnalytics($websiteId, $sourceId, $reportInfo, $resultDate, $clearExisting = true) {
		$websiteId = intval($websiteId);
		$sourceId = intval($sourceId);
		$resultDate = addslashes($resultDate);
	
		if ($clearExisting) {
			$whereCond = "website_id=$websiteId and report_date='$resultDate' and source_id='$sourceId'";
			$this->dbHelper->deleteRows('website_analytics', $whereCond);
		}	
	
		$reportInfo['bounceRate'] = round($reportInfo['bounceRate'], 2);
		$reportInfo['avgSessionDuration'] = round(($reportInfo['avgSessionDuration']/60), 2);
		$reportInfo['website_id'] = $websiteId;
		$reportInfo['source_id'] = $sourceId;
		$reportInfo['report_date'] = $resultDate;
		$this->dbHelper->insertRow('website_analytics', $reportInfo);
	}
	
}
?>