function getDocStock($doc,$prod,$from,$to,$location,$batch,$yrid,$serial=null,$company=null,$non_production=null)
    {     
        $db=JFactory::getDBO();
        $from = ($from)?date("Y-m-d", strtotime($from)):'2020-01-01';
        $to   = ($to)?date("Y-m-d", strtotime($to)):date("Y-m-d");
        
        $production_gid = ErpHelpersErp::getResultColumn('id','#__usergroups'," AND `production`='1' ORDER BY `id` ASC ");
        
        $where ='';
        if($location && $non_production!=1)
        {
                $where = " AND (   b.gid IN ($location)  )"; 
        }
        else if($non_production==1 && $production_gid)
        {
             $production_gid = ($production_gid)?implode(',',$production_gid):'';
             $where = " AND (  b.gid NOT IN ($production_gid)  )"; 
        }
 
        if($batch)
        {
           // $where .= " AND  b.`batch` IN ($batch) ";
        }        
        /*if($yrid)
        {
            $where .= " AND  b.`yrid` IN ($yrid) ";
        }*/        
        if($serial)
        {
            $where2 = " AND  a.`serialno` LIKE '$serial' ";            
            $whereb = " AND  b.`serialno` LIKE '$serial' ";
        }
        
        if($company)
        { 
            $where .= " AND ( b.`company` = '$company' OR b.`company` = '0' ) ";   
        }
        
      
        
        $productdetail = ErpHelpersErp::getObject('#__crm_mproducts'," AND `id`='$prod' "); 

        if($doc=='2,3')
        {
            /*$table ="#__ierp_grn_grid"; 
            $sql ="SELECT count(a.id) as qty FROM `#__ierp_grnserialgrid` as a LEFT JOIN `$table` as b on a.grnno=b.grnid AND a.prodid=b.propid
                   WHERE 1 AND b.doc IN ($doc) AND b.`propid`='$prod' AND b.date BETWEEN '$from' AND '$to' $where $where2 AND b.`status`!=2   ";*/
                   
            /*$table ="#__ierp_grnserialgrid";     
              $sql ="SELECT count(b.id) as qty FROM $table as b WHERE 1  AND `prodid`='$prod' AND date BETWEEN '$from' AND '$to' 
              $where $whereb AND `status`!=2 ";     */   
              
              
            //if($productdetail->barcodetype=='1')//Quantity Code = 1
            //{
                  $table ="#__ierp_grn_grid";     
                  $sql ="SELECT sum(qty) as qty FROM $table as b WHERE 1  AND `propid`='$prod' AND date BETWEEN '$from' AND '$to' 
                  $where  AND `status`!=2 ";     
            /*}
            else
            {
               $table ="#__ierp_grn_grid";
               
               $sql ="SELECT count(a.id) FROM `#__ierp_grnserialgrid` as a LEFT JOIN `$table` as b on a.grnno=b.grnid AND a.prodid=b.propid
               WHERE 1 AND b.doc IN ($doc) AND b.`propid`='$prod' AND b.date BETWEEN '$from' AND '$to' $where $where2 AND b.`status`!=2 ";      
                   
            } */
             // echo $sql;     
         
        }
        else if($doc=='4'||$doc=='5'||$doc=='6')
        {
            $table ="#__ierp_stkrequest_grid";
            $sql ="SELECT sum(qty) as qty FROM $table as b WHERE 1 AND doc IN ($doc)  AND `propid`='$prod' AND date BETWEEN '$from' AND '$to' $where $whereb AND `status`!=2 ";
           //echo $sql.'<br />';die;
        } 
        else if($doc=='12')
        {
            $table = "#__ierp_sale_grid";  
         
            /*$sql = "SELECT count(a.id) FROM `#__ierp_grnserialgrid` as a  join `$table` as b  on a.serialno=b.serialno 
            WHERE 1 AND b.doc IN ($doc) AND b.`propid`='$prod' AND b.date BETWEEN '$from' AND '$to' $where $where2 AND b.`status`!=2 ";*/
            
            $sql ="SELECT sum(qty) as qty FROM $table as b WHERE 1 AND doc IN ($doc)  AND `propid`='$prod' 
            AND date BETWEEN '$from' AND '$to' $where  $whereb  AND `status`!=2 ";
            
            
            //echo $sql.'<br />';
        
        }
        else if($doc=='14')
        {
            $table = "#__ierp_salesret_grid";  
            
            /*$sql = "SELECT count(a.id) FROM `#__ierp_grnserialgrid` as a  join `$table` as b  on a.serialno=b.serialno 
            WHERE 1 AND b.doc IN ($doc) AND b.`propid`='$prod' AND b.date BETWEEN '$from' AND '$to' $where $where2 AND b.`status`!=2 ";*/
            
            $sql ="SELECT sum(qty) as qty FROM $table as b WHERE 1 AND doc IN ($doc)  AND `propid`='$prod' 
            AND date BETWEEN '$from' AND '$to' $where $whereb  AND `status`!=2 ";
        
        }
        else if($doc=='15')
        {
            $table = "#__ierp_purchaseret_grid";  
            
           /* $sql = "SELECT count(a.id) FROM `#__ierp_grnserialgrid` as a  join `$table` as b  on a.serialno=b.serialno 
            WHERE 1 AND b.doc IN ($doc) AND b.`propid`='$prod' AND b.date BETWEEN '$from' AND '$to' $where $where2 AND b.`status`!=2 ";*/
             $sql ="SELECT sum(qty) as qty FROM $table as b WHERE 1 AND doc IN ($doc)  AND `propid`='$prod' 
            AND date BETWEEN '$from' AND '$to' $where $whereb  AND `status`!=2 ";
        
        }
        else if($doc=='13')
        {
            $table = "#__ierp_damage_grid";  
            $sql ="SELECT sum(qty) as qty FROM $table as b WHERE 1 AND doc IN ($doc)  AND `propid`='$prod' 
            AND date BETWEEN '$from' AND '$to' $where $whereb  AND `status`!=2  AND `repair`=0  ";
            
        
        }
        else if($doc=='11')
        {
            $table = "#__ierp_sale_grid";
            $sql ="SELECT sum(qty) as qty FROM $table as b WHERE 1  AND `propid`='$prod' 
            AND date BETWEEN '$from' AND '$to' $where $whereb  AND `status`!=2 ";
          
        }  
        else if($doc=='23')
        {
            $table = "#__ierp_adjustment_grid";
            $sql ="SELECT sum(qty) as qty FROM $table as b WHERE 1  AND `propid`='$prod' 
            AND date BETWEEN '$from' AND '$to' $where $whereb  AND `status`!=2 AND doc IN ($doc) ";
          
        }  
        else if($doc=='24')
        {
            $table = "#__ierp_adjustment_grid";
            $sql ="SELECT sum(qty) as qty FROM $table as b WHERE 1  AND `propid`='$prod' 
            AND date BETWEEN '$from' AND '$to' $where $whereb  AND `status`!=2  AND doc IN ($doc)";
        
          
        }  
         else if($doc=='25')
        {
            $table = "#__ierp_workorder_grid";
            $sql ="SELECT sum(qty) as qty FROM $table as b WHERE 1  AND `propid`='$prod' 
            AND date BETWEEN '$from' AND '$to' $where $whereb  AND `status`!=2  AND `fromtostatus`=1  ";
        
          
        }  
       
        $db->setQuery($sql); 
        $result=$db->loadResult();
        $result = ($result!=NULL)?$result:0;
        return $result;
    }