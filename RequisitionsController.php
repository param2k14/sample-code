<?php
class RequisitionsController extends Controller {

	/*
	 * Lists all Requisitions of Currently Logged-In user
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
		Yii::app()->clientScript->registerScriptFile(Yii::app()->theme->baseUrl.'/js/requisitionslisting.js');
		Yii::app()->clientScript->registerScript('helpers', 'baseUrl = '.CJSON::encode(Yii::app()->baseUrl).';');
		/*************************************/
		
		
		$reqFilter = array();
		$model=new RequisitionRequests();		
		$reqFilter['is_active']=1;
		
		if (isset($_GET['RequisitionRequests'])) {
			$reqFilter = $_GET['RequisitionRequests'];
			$model->attributes = $_GET['RequisitionRequests'];
			
		}
		$reqFilter['created'] ="";
		if (isset($_GET['created']) && !empty($_GET['created'])) {
			$reqFilter['created'] = date("Y-m-d",strtotime($_GET['created']));
		}
		if($userType=="nurse") {
			$reqFilter['requester']=GooIdentity::getUserSessionDetail('user','id');
			if(isset($_GET['RequisitionRequests']['user_assigned'])) {
		      $reqFilter['assigned_to'] = $_GET['RequisitionRequests']['user_assigned'];
			}
		}
		$nurselist = "";
		if($userType=="tech"){
			$reqFilter['assigned_to']=GooIdentity::getUserSessionDetail('user','id');
			if(isset($_GET['RequisitionRequests']['requester_user'])) {
			  $reqFilter['requester'] = $_GET['RequisitionRequests']['requester_user'];
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
		$reqModel=new RequisitionService();
		//Utilities::pr($reqFilter);
		$reqList = $reqModel->search($reqFilter,false);
		//Utilities::pr($reqFilter);
		//$reqList = $model->search($reqFilter);
		//$model->unsetAttributes();
		
		//code to show search window starts here
		//Utilities::pr($reqFilter);
		$searchVal = Yii::app()->request->getQuery('RequisitionRequests');
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
		$this->render('myRequisitions', array(
        	'model' => $model,'reqList'=>$reqList,'filterData'=>$reqFilter,'arrStatus'=>$arrStatus,
	    	'nurselist'=>$nurselist,'vendors'=>$vendors,'roleId'=>$userRoleId,'extraParams'=>$extraParams
	    ));
	}
	
	/*
	 * Function to add/update requisition
	 */
	public function actionAddEdit() {
		Yii::app()->clientScript->registerScript('helpers', 'baseUrl = '.CJSON::encode(Yii::app()->baseUrl).';');
		Yii::app()->clientScript->registerScriptFile(Yii::app()->theme->baseUrl.'/js/requisitions_creation.js');
		
		$requisitions_id=Yii::app()->request->getPost('requisitions_id');
		$userRole=GooIdentity::getUserSessionDetail('user','role_id');

		//if add requisition form is submitted
		if(isset($_POST['RequisitionRequests']))
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
				$arrReqDetails['requester_user']=$userId;
				$arrReqDetails['description']=$data['RequisitionRequests']['description'];
				$arrReqDetails['requested_in']=$data['RequisitionRequests']['requested_in'];
				$arrReqDetails['transfer_to']=$data['RequisitionRequests']['transfer_to'];
				if(isset($data['RequisitionRequests']['requested_for_user']))
					$arrReqDetails['requested_for_user']=$data['RequisitionRequests']['requested_for_user'];
				else 
					$arrReqDetails['requested_for_user']=$userId;
				$arrReqDetails['items'] = CJSON::encode($arrItems);
				$arrReqDetails['skip_existing_items_check']=$data['skip_existing_items_check'];
				try {
					$arrReqDetails['id']=null;
					if(isset($data['RequisitionRequests']['id']))
						$arrReqDetails['id']=$data['RequisitionRequests']['id'];
					$objRequisitionService=new RequisitionService();
					if($arrReqDetails['id'] !=""){
						$requisitionRequestId=$objRequisitionService->update($arrReqDetails);
						$msg = "updated";
						//Yii::app()->user->setFlash('success', "Requisition has been updated successfully.");
					}else{
						$requisitionRequestId=$objRequisitionService->create($arrReqDetails);
						if(Utilities::is_tech()){
							//assing requistion to tech if its created by tech on the behalf of nurse
								$filter['assign_to']=$userId;
								$filter['id']=$requisitionRequestId;
								$objRequisitionService->update($filter);						
							//assing requistion ends here
						}
						$msg = "created";
						//Yii::app()->user->setFlash('success', "Requisition has been created successfully.");
					}
					$arrResult = CJSON::decode($requisitionRequestId);					
					if(Yii::app()->request->isAjaxRequest){
						echo $msg;
						exit();
					}
					else{
					 $this->redirect(array('/my-requisitions'), true);
					  
					}
					
					
				}
				catch(Exception $e) {
					
					$code = $e->getCode();
					$arrExistingItems = array();
					if($code==400){
						echo $e->getMessage();
					}
					if($code==417){
						$arrResult = CJSON::decode($e->getMessage());						
						if(!empty($arrResult)){							
							foreach($arrResult['existing_items'] as $key=>$val){
								array_push($arrExistingItems,$val['item_id']);
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
				$this->redirect(array('/my-requisitions'), true);
			}
		}//ends
		$nurseData = array();
		/************fetch nurse list*************************/
		$defaultDepartment=GooIdentity::getUserSessionDetail('user','department_id');
		if(Utilities::is_tech()){						
			$nurseFilter['role']=Utilities::getUserRole('nurse',true);	
			$nurseFilter['status']="active";	
			//$nurseFilter['department']=$defaultDepartment;//tech can create requisition for only those nurse who belongs to his department
			$objUserService=new UserService();
			$nurseList = $objUserService->search($nurseFilter,true);
			$nurseData=CHtml::listData($nurseList['users'],'id','name');
			if(count($nurseList['users'])==0){
			  Yii::app()->user->setFlash('error', "No nurse user is found in your department.");
			  $this->redirect(array('/my-requisitions'), true);
			}					
			$defaultDepartment=$nurseList['users'][0]['department_id'];  //default department of first nurse
					
		}
		
		//inventory list
		$objInventoryService=new InventoryService();
		$inventoryFilter['department']=$defaultDepartment;
		$inventoryFilter['is_active']=1;
		$inventoryFilter['is_main']=0;
		$inventoriesData=$objInventoryService->search($inventoryFilter,false);
		$inventories=CHtml::listData($inventoriesData->getData(),'id','name');
		
		
		/************fetch nurse list ends*************************/				
			
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

		
		/*$objDepartmentService=new DepartmentService();
		$locationsData=$objDepartmentService->loadById($defaultDepartment,array('with'=>'locations'),false);
		if(isset($locationsData->locations)) {
			$locations=CHtml::listData($locationsData->locations,'id','name');
		}*/
		$model=new RequisitionRequests();
		if($requisitions_id>0) {
			$objRequisitionService=new RequisitionService();
			$requisition=$objRequisitionService->loadById($id,false);
			$model->attributes=$requisition->getData();
		}
		else {
		  $model->requested_in=GooIdentity::getUserSessionDetail('user','inventory_id');
		}
		
		$this->render('addEdit',array('model'=>$model,'inventories'=>$inventories,'mainInventories'=>$mainInventories,'nurseData'=>$nurseData));
	}
	/*
	 * Function to show detail of a particular Requisition
	*/
	public function actionView($id=0) {		
	    Yii::app()->clientScript->registerScriptFile(Yii::app()->theme->baseUrl.'/js/requisitions_creation.js');
	 
		Yii::app()->clientScript->registerScriptFile(Yii::app()->theme->baseUrl.'/js/requisitions.js');
		Yii::app()->clientScript->registerScript('helpers', 'baseUrl = '.CJSON::encode(Yii::app()->baseUrl).';');
			$request = array();
		if($id>0) {
			$filter = array();
			$filter['requisition']=$id;
			$filter['status']='active';
			$filter['with']='requisitionRequestedItems,defaultVendor';				
			$objItemService=new ItemService();
			$requisitionItems=$objItemService->search($filter,false);
			$requstData=$requisitionItems->getData();
			if(!empty($requstData))
				$request=$requstData['0']->requisitions['0'];
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
		$mainInvFilter['is_main']=0;

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
				$this->renderPartial('viewRequisitions',array('inventories'=>$inventories,'mainInventories'=>$mainInventories,'requisitionItems'=>$requisitionItems,'request'=>$request,'arrExtraParams'=>$arrExtraParams),false,true);
			elseif($request->status==0)
				$this->renderPartial('updateRequisitions',array('inventories'=>$inventories,'mainInventories'=>$mainInventories,'requisitionItems'=>$requisitionItems,'request'=>$request,'arrExtraParams'=>$arrExtraParams),false,true);
		}
		else {
			$this->render('updateRequisitions',array('inventories'=>$inventories,'mainInventories'=>$mainInventories,'requisitionItems'=>$requisitionItems,'request'=>$request,'arrExtraParams'=>$arrExtraParams));
		}
	
	
	}
	
	
	/*
	 * Delete Requisition for passed ID
	*/
	public function actionDelete($id) {
	    Yii::app()->clientScript->registerScriptFile(Yii::app()->theme->baseUrl.'/js/requisitions_creation.js');
	   	$model = new RequisitionRequests;
		if(!isset($id)) {
			$error = "Requisition parameters are missing";
		}
		try {
			$objRequisitionService=new RequisitionService();
			$is_deleted=$objRequisitionService->delete($id);
			if($is_deleted) {
				//Yii::app()->user->setFlash('success', "");
				$error = "deleted";
				
			}
			else {
				$error = "Unable to delete requisition";
			}
		}
		catch(Exception $e) {
			$error =  $e->getMessage();
		}		
		echo $error;
	}
	/*
	 * Delete Requisition for passed ID
	*/
	public function actionComplete($id) {
		if(!isset($id)) {
			Yii::app()->user->setFlash('error', "Requisition parameters are missing");			
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
		    $arrReqDetails['id'] = $data['RequisitionRequests']['id'];
		  }
			$objRequisitionService=new RequisitionService();
			
			$arrReqDetails['status'] = 2;	
			$arrReqDetails['skip_existing_items_check']=1;	
			$requisitionRequestId=$objRequisitionService->update($arrReqDetails);
			if($requisitionRequestId) {
			    echo "completed";
				//Yii::app()->user->setFlash('success', "Requisition has been marked as completed.");
				exit();
	
			}
			else {
				echo "Requisition parameters are missing";	
				exit();
			}
		}
		catch(Exception $e) {
			echo "Error: ".$e->getMessage();
			exit();
		}
		exit();
		//$this->redirect(array('/my-requisitions'), true);
	}
	
	public function actionGet_inventories($id) {
	  // $id = Yii::app()->request->getQuery('id');
	
	  $objUserService=new UserService();
	   
	  //Nurses
	  $nurses = array();
	  $nurseFilter['status']="active";
	  $nurseList = $objUserService->loadById($id,$nurseFilter,true);
	  //inventory list
	  $defaultDepartment=$nurseList['user']['department_id'];  //default department of first nurse
	  $objInventoryService=new InventoryService();
	  $inventoryFilter['department']=$defaultDepartment;
	  $inventoryFilter['is_active']=1;
	  $inventoryFilter['is_main']=0;
	  $inventoriesData=$objInventoryService->search($inventoryFilter,false);
	  if($inventoriesData->itemCount>0) {
	    $inventories=$inventoriesData->getData();
	    $inventorylistList=CHtml::listData($inventories,'id','name');
	    $this->renderPartial('locations',array('inventories'=>$inventorylistList));
	  }
	  else {
	    exit();
	  }
	  $inventories=CHtml::listData($inventoriesData->getData(),'id','name');
	}
	
	
}