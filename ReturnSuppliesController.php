<?php
class ReturnSuppliesController extends Controller {

	/*
	 * Lists all ReturnSupplies of Currently Logged-In user
	 */
	public function actionIndex() {
		$objUserService=new UserService();
		$arrStatus = array();
		$extraParams=array();
		$vendorFilter['is_active']=1;
		$userType = Utilities::getUserType();
		$page_size=Yii::app()->request->getQuery('page_size');
		if($page_size==""){
		  $page_size = Yii::app()->params['paginationLimit'];
		}
		$userRoleId=GooIdentity::getUserSessionDetail('user','role_id');
		if(Utilities::is_nurse()){
		  $arrStatus =array("0"=>"Pending","1"=>"Assigned","2"=>"Completed");		  
		  $vendorFilter['role']=Utilities::getUserRole('tech',true);
		}elseif(Utilities::is_tech()){
		  $arrStatus =array("1"=>"Assigned","2"=>"Completed");
		  $vendorFilter['role']=Utilities::getUserRole('nurse',true);;
		}
		
		$vendorsData=$objUserService->search($vendorFilter,false);
		$vendors=CHtml::listData($vendorsData->getData(),'id','name');
		Yii::app()->clientScript->registerScriptFile(Yii::app()->theme->baseUrl.'/js/return_supply/returnsupply.js');
		Yii::app()->clientScript->registerScript('helpers', 'baseUrl = '.CJSON::encode(Yii::app()->baseUrl).';');
		/*************************************/
		
		
		$reqFilter = array();
		$model=new ReturnSupplyRequests();		
		
		if (isset($_GET['ReturnSupplyRequests'])) {
			$reqFilter = $_GET['ReturnSupplyRequests'];
			$model->attributes = $_GET['ReturnSupplyRequests'];
			
		}
		$reqFilter['created'] ="";
		if (isset($_GET['created']) && !empty($_GET['created'])) {
			$reqFilter['created'] = date("Y-m-d",strtotime($_GET['created']));
		}
		if($userType=="nurse") {
			$reqFilter['requester']=GooIdentity::getUserSessionDetail('user','id');
			if(isset($_GET['ReturnSupplyRequests']['user_assigned'])) {
		      $reqFilter['assigned_to'] = $_GET['ReturnSupplyRequests']['user_assigned'];
			}
		}
		$nurselist = "";
		if($userType=="tech"){
			$reqFilter['assigned_to']=GooIdentity::getUserSessionDetail('user','id');
			if(isset($_GET['ReturnSupplyRequests']['requester_user'])) {
			  $reqFilter['requester'] = $_GET['ReturnSupplyRequests']['requester_user'];
			}
			$nurselist = $vendors;
		}		
		
		if($page_size!=""){
		  $reqFilter['page_size'] = $page_size;
		}else{
		  $reqFilter['page_size'] = 10;
		}
		//Utilities::pr($reqFilter,false);
		//$reqFilter['order']='[{"column":"last_modified","direction":"DESC"}]';
		$objReturnSupplyService=new ReturnSupplyService();
		$reqList=$objReturnSupplyService->search($reqFilter,false);

		
		//$reqList = $model->search($reqFilter);
		//$model->unsetAttributes();
		
		//code to show search window starts here
		//Utilities::pr($reqFilter);
		$searchVal = Yii::app()->request->getQuery('ReturnSupplyRequests');
		$searcDiv = "display:none";
		if(count($searchVal)>0){
    		foreach($searchVal as $key=>$val){
    		  if($val !="" || $reqFilter['created']!="")
    		     $searcDiv = "display:block";
    		}		
		}	
		$extraParams['searcDiv']=$searcDiv;
		//code to show search window ends here
		
		
		$extraParams['page_size']=$reqFilter['page_size'];
		$this->render('my_returnsupplies', array(
        	'model' => $model,'reqList'=>$reqList,'filterData'=>$reqFilter,'arrStatus'=>$arrStatus,
	    	'nurselist'=>$nurselist,'vendors'=>$vendors,'roleId'=>$userRoleId,'extraParams'=>$extraParams
	    ));
	}
	
	/*
	 * Function to add/update returnsupply
	 */
	public function actionAddEdit() {
		Yii::app()->clientScript->registerScriptFile(Yii::app()->theme->baseUrl.'/js/return_supply/returnsupply_creation.js');
		$returnSupplyRequestId=Yii::app()->request->getPost('request_id');
		$userRole=GooIdentity::getUserSessionDetail('user','role_id');

		//if add returnsupply form is submitted
		if(isset($_POST['ReturnSupplyRequests']))
		{		
			$userId=GooIdentity::getUserSessionDetail('user','id');
			if(empty($userId)) {
				Yii::app()->user->setFlash('error', "User not found");
			}
			else{
				$data = $_POST;
				$cnt = 0;
				foreach($data['qty'] as $key=>$val){
						$arrItems[$cnt]['item_id']=$key;
						$arrItems[$cnt]['requested_qty']=$val;
						$cnt++;
				}
				$reqFilter['IRC'] = "";
				if(!empty($data['reference_requisition_id'])){
				  $reqFilter['IRC']=$data['reference_requisition_id'] ;
				  
				}
				if(!empty($data['ReturnSupplyRequests']['reference_requisition_id'])){
				  $reqFilter['IRC']=$data['ReturnSupplyRequests']['reference_requisition_id'] ;
				
				}
				if($reqFilter['IRC'] !=""){
    				$reqModel=new RequisitionService();
    				$reqDetail = $reqModel->search($reqFilter,true);
    				if(count($reqDetail['requests'])>0){
    				  $requi_id = $reqDetail['requests'][0]['id'];
    				  $arrReqDetails['reference_requisition_id']=$requi_id;
    				}
				}
				
				$arrReqDetails['requester_user']=$userId;
				$arrReqDetails['description']=$data['ReturnSupplyRequests']['description'];
				$arrReqDetails['requested_in']=$data['ReturnSupplyRequests']['requested_in'];
				$arrReqDetails['transfer_to']=$data['ReturnSupplyRequests']['transfer_to'];
				if(isset($data['ReturnSupplyRequests']['requested_for_user']))
					$arrReqDetails['requested_for_user']=$data['ReturnSupplyRequests']['requested_for_user'];
				else 
					$arrReqDetails['requested_for_user']=$userId;
				
				$arrReqDetails['items'] = CJSON::encode($arrItems);
				try {
					$arrReqDetails['id']=null;
					if(isset($data['ReturnSupplyRequests']['id']))
						$arrReqDetails['id']=$data['ReturnSupplyRequests']['id'];
					$objReturnSupplyService=new ReturnSupplyService();
					
					//if return supply is created using existing requisition than it will not have invenoty id
					
					if($arrReqDetails['requested_in']=="")
					  $arrReqDetails['requested_in']= null ;

					if($arrReqDetails['id'] !=""){
						$returnSupplyRequestId=$objReturnSupplyService->update($arrReqDetails);
						$msg = "updated";
						//Yii::app()->user->setFlash('success', "ReturnSupply has been updated successfully.");
					}else{
						$returnSupplyRequestId=$objReturnSupplyService->create($arrReqDetails);
						if(Utilities::is_tech()){
							//assing requistion to tech if its created by tech on the behalf of nurse
								$filter['assign_to']=$userId;
								$filter['id']=$returnSupplyRequestId;
								$objReturnSupplyService->update($filter);						
							//assing requistion ends here
						}
						$msg = "created";
						//Yii::app()->user->setFlash('success', "ReturnSupply has been created successfully.");
					}
					$arrResult = CJSON::decode($returnSupplyRequestId);					
					if(Yii::app()->request->isAjaxRequest){
						echo $msg;
						exit();
					}
					else{
					 $this->redirect(array('/returnsupplies'), true);
					  
					}
					
					
				}
				catch(Exception $e) {	
				    if(isset($e->statusCode)){
				      $code = $e->statusCode;
				    }else{
				      $code = $e->getCode;
				    }
					$arrExistingItems = array();
					if($code==400){
						echo $e->getMessage();					}
					if($code==417){
						$arrResult = CJSON::decode($e->getMessage());	
						if(!empty($arrResult)){							
							foreach($arrResult['quantityValidation'] as $key=>$val){
								$arrExistingItems[$val['item_id']] = $val['item_id']."=".$val['maxQuantity'];
							}
							echo $strItems = implode(",",$arrExistingItems);
							exit();
						}
					}	
					else{	//if requistion is added
							echo $e->getMessage();								
							exit();
								
						}
										
				}
				$this->redirect(array('/list'), true);
			}
		}//ends
		$nurseData = array();
		/************fetch nurse list*************************/
		
		if(Utilities::is_tech()){						
			$nurseFilter['role']=Utilities::getUserRole('nurse',true);	
			$nurseFilter['status']="active";			
			$objUserService=new UserService();
			$nurseList = $objUserService->search($nurseFilter,true);
			$nurseData=CHtml::listData($nurseList['users'],'id','name');
		}
		/************fetch nurse list ends*************************/				
		$defaultDepartment=GooIdentity::getUserSessionDetail('user','department_id');
		$objInventoryService=new InventoryService();
		$inventoryFilter['department']=$defaultDepartment;
		$inventoryFilter['is_active']=1;
		$inventoryFilter['is_main']=0;
		$inventoriesData=$objInventoryService->search($inventoryFilter,false);
		$inventories=CHtml::listData($inventoriesData->getData(),'id','name');		
		$locations = array();
		
		//location list starts here
		  $mainInventories=array();
		  $mainInvFilter['status']='active';
		  $mainInvFilter['is_main']=1;	
		 // $mainInvFilter['department']=$defaultDepartment;
		  $mainInvData=$objInventoryService->search($mainInvFilter,false);
		  if($mainInvData->itemCount>0) {
			  $mainInventories=$mainInvData->getData();
			  $mainInventories=CHtml::listData($mainInventories,'id','name');
		  }
		//location list ends here
		
		/*$locations = array();
		$objLocationService=new LocationService();
		$locationsData=$objLocationService->search(array('departments'=>$defaultDepartment),false);
		if($locationsData->itemCount>0) {
		  $locationsList=$locationsData->getData();
		  $locations=CHtml::listData($locationsList,'id','name');
		}*/
				
		$model=new ReturnSupplyRequests();
		if($returnSupplyRequestId>0) {
		    $objReturnSupplyService=new ReturnSupplyService();
			$returnsupply=$objReturnSupplyService->loadById($id,false);
			$model->attributes=$returnsupply->getData();
		}
		else {
		  $model->requested_in=GooIdentity::getUserSessionDetail('user','inventory_id');
		}
		
		$this->render('return_supply',array('model'=>$model,'inventories'=>$inventories,'mainInventories'=>$mainInventories,'nurseData'=>$nurseData));
	}
	/*
	 * Function to show detail of a particular ReturnSupply
	*/
	public function actionView($id=0) {		
	  
	    Yii::app()->clientScript->registerScriptFile(Yii::app()->theme->baseUrl.'/js/return_supply/returnsupply_creation.js');
		Yii::app()->clientScript->registerScriptFile(Yii::app()->theme->baseUrl.'/js/return_supply/returnsupply.js');
		
		$request = array();
		if($id>0) {
		 // echo "sdfdsf";exit();
			$filter = array();
			$filter['return_supply']=$id;
			$filter['status']='active';
			$filter['with']='returnSupplyRequestedItems,defaultVendor';				
			$objItemService=new ItemService();
			$returnsupplyItems=$objItemService->search($filter,false);	
			$requstData=$returnsupplyItems->getData();			
			if(!empty($requstData))
				$request=$requstData['0']->returnSupplies['0'];
			
		}	
		if(Utilities::is_tech()){
		  $userId = $request->requested_for_user;
		  $objUserService=new UserService();
		  $userDataData=$objUserService->loadById($request->requested_for_user,true);
		  $defaultDepartment=$userDataData['user']['department_id'];
		}else{
		  $defaultDepartment=GooIdentity::getUserSessionDetail('user','department_id');
		}
		$objInventoryService=new InventoryService();
		$inventoryFilter['department']=$defaultDepartment;
		$inventoryFilter['is_active']=1;
		$inventoriesData=$objInventoryService->search($inventoryFilter,false);
		$inventories=CHtml::listData($inventoriesData->getData(),'id','name');
		
		//location list starts here
		$mainInventories=array();
		$mainInvFilter['status']='active';
		$mainInvFilter['is_main']=1;
		//$mainInvFilter['department']=$defaultDepartment;
		$mainInvData=$objInventoryService->search($mainInvFilter,false);
		if($mainInvData->itemCount>0) {
		  $mainInventories=$mainInvData->getData();
		  $mainInventories=CHtml::listData($mainInventories,'id','name');
		}
		//location list ends here
		
		$refUrl = Yii::app()->request->getUrlReferrer();
		$arrExtraParams = array();
		$arrExtraParams['refUrl'] =$refUrl;
		//if (Yii::app()->request->isAjaxRequest)
		$isAjaxRequest = Yii::app()->request->getPost('isAjaxRequest');
		if($isAjaxRequest==1) {		 
			if($request->status==1 || $request->status==2)	//if requsition is pending 
				$this->renderPartial('viewReturnsupply',array('inventories'=>$inventories,'mainInventories'=>$mainInventories,'returnsupplyItems'=>$returnsupplyItems,'request'=>$request,'arrExtraParams'=>$arrExtraParams),false,true);
			elseif($request->status==0)
				$this->renderPartial('updateReturnsupply',array('inventories'=>$inventories,'mainInventories'=>$mainInventories,'returnsupplyItems'=>$returnsupplyItems,'request'=>$request,'arrExtraParams'=>$arrExtraParams),false,true);
		}
		else {
			$this->render('updateReturnsupply',array('inventories'=>$inventories,'mainInventories'=>$mainInventories,'returnsupplyItems'=>$returnsupplyItems,'request'=>$request,'arrExtraParams'=>$arrExtraParams));
		}	
	
	}
	
	
	/*
	 * Delete ReturnSupply for passed ID
	*/
	public function actionDelete($id) {
	    Yii::app()->clientScript->registerScriptFile(Yii::app()->theme->baseUrl.'/js/return_supply/returnsupply_creation.js');
	   	$model = new ReturnSupplyRequests;
		if(!isset($id)) {
			$error = "ReturnSupply parameters are missing";
		}
		try {
			$objReturnSupplyService=new ReturnSupplyService();
			$is_deleted=$objReturnSupplyService->delete($id);
			if($is_deleted) {
				//Yii::app()->user->setFlash('success', "");
				$error = "deleted";
				
			}
			else {
				$error = "Unable to delete returnsupply";
			}
		}
		catch(Exception $e) {
			$error =  $e->getMessage();
		}		
		echo $error;
	}
	/*
	 * Delete ReturnSupply for passed ID
	*/
	public function actionComplete($id) {
		if(!isset($id)) {
			Yii::app()->user->setFlash('error', "Returnsupply parameters are missing");			
		}
		try {
		  if(isset($_POST['assigned_qty']))
		  {
		      $data = $_POST;
		      $cnt = 0;
		      foreach($data['assigned_qty'] as $key=>$val){
		        $arrItems[$cnt]['item_id']=$key;
		        $arrItems[$cnt]['assigned_qty']=$val;
		        $cnt++;		      
		    }
		    $rcnt = 0;
		    foreach($data['requested_qty'] as $key=>$val){
		      $arrItems[$rcnt]['requested_qty']=$val;
		      $rcnt++;
		    }
		    $arrReqDetails['items'] = CJSON::encode($arrItems);		   
		    $arrReqDetails['id'] = $data['ReturnSupplyRequests']['id'];
		  }
			$objReturnSupplyService =new ReturnSupplyService();
			
			$arrReqDetails['status'] = 2;	
			$arrReqDetails['skip_existing_items_check']=1;	
			$returnSupplyRequestId=$objReturnSupplyService->update($arrReqDetails);
			if($returnSupplyRequestId) {
			    echo "completed";
				//Yii::app()->user->setFlash('success', "ReturnSupply has been marked as completed.");
				exit();
	
			}
			else {
				echo "ReturnSupply parameters are missing";	
				exit();
			}
		}
		catch(Exception $e) {
			echo "Error: ".$e->getMessage();
			exit();
		}
		exit();
		//$this->redirect(array('/returnsupplies'), true);
	}
	
	
	// data provider for EJuiAutoCompleteFkField for PostCodeId field
	public function actionFindIrc() {
	  $q = $_GET['term'];
	  $reqFilter['IRC']=$q ;
	  $reqFilter['status']=2 ;
	  $reqFilter['requester']=GooIdentity::getUserSessionDetail('user','id');
	  	  if (isset($q)) {	   
	    $reqModel=new RequisitionService();
	    $reqList = $reqModel->search($reqFilter,true);
	    if (!empty($reqList)) {
	      $out = array();
	      foreach ($reqList['requests'] as $key=>$val) {
	       // Utilities::pr($val,false);
	        $out[] = array(
	            // expression to give the string for the autoComplete drop-down
	            'label' => $val['IRC'],
	            'value' => $val['IRC'],
	            'id' => $val['IRC'], // return value from autocomplete
	        );
	      }
	      echo CJSON::encode($out);
	      Yii::app()->end();
	    }
	  }
	}
	
}