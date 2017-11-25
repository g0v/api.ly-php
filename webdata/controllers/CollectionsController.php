<?php

class CollectionsController extends Pix_Controller
{
    protected function getWhere($q)
    {
        $db = Pix_Table::getDefaultDb();

        $terms = array();
        foreach ($q as $k => $condiction) {
            foreach ($condiction as $op => $value) {
                if ($op == '$contains') {
                    $terms[] = $db->column_quote($k) . '@> ARRAY[' . $db->_pdo->quote($value) . ']';
                }
            }
        }
        if (!$terms) {
            return 'TRUE';
        }

        return implode(' AND ', $terms);
    }

    protected function getMotion($row)
    {
        $db = Pix_Table::getDefaultDb();

        $sql = sprintf("SELECT chair, date, time_start, time_end, id AS sitting_id FROM calendar WHERE sitting = " . $db->_pdo->quote($row['sitting']));
        $res = $db->query($sql);
        $row['dates'] = array();
        while ($row2 = $res->fetch_assoc()) {
            $row['dates'][] = $row2;
        }
        return $row;
    }

    protected function getBill($row)
    {
        $db = Pix_Table::getDefaultDb();

        $row['data'] = json_decode($row['data']);
        $row['doc'] = json_decode($row['doc']);
        $row['sponsors'] = json_decode($row['sponsors']);
        $row['cosponsors'] = json_decode($row['cosponsors']);
        $row['motions'] = array();
        $sql = sprintf("SELECT sitting,sitting_id,resolution,status,motions.committee,motion_class,agenda_item,item FROM motions JOIN sittings ON (sitting_id = sittings.id) WHERE bill_id = %s", $db->_pdo->quote($row['bill_id']));
        $res = $db->query($sql);
        while ($row2 = $res->fetch_assoc()) {
            $row2 = $this->getMotion($row2);
            $row['motions'][] = $row2;
        }
        return $row;
    }

    public function indexAction()
    {
        list(, , , $table, $id, $key) = explode('/', $this->getURI());
        $db = Pix_Table::getDefaultDb();
        if ($table == 'bills' and $id) {
            $res = $db->query(sprintf(
                "SELECT data, doc"
                . ",bill_id,bill_ref,summary,proposed_by,abstract,report_of,reconsideration_of,bill_type,sitting_introduced"
                . ", ARRAY_TO_JSON(case when ttsbills.sponsors is null then bills.sponsors else ttsbills.sponsors end) AS sponsors"
                . ", ARRAY_TO_JSON((case when ttsbills.cosponsors is null then bills.cosponsors else ttsbills.cosponsors end)) as cosponsors"
                . " FROM bills LEFT JOIN (select sponsors, cosponsors, bill_ref, sitting_introduced, 'legislative'::text as bill_type from ttsbills) ttsbills USING (bill_ref) "
                . " WHERE %s = %s LIMIT 1",
                $db->column_quote(rtrim($table, 's') . '_ref'),
                $db->_pdo->quote($id)
            ));
            $row = $res->fetch_assoc();
            $row = $this->getBill($row);
            if ($key) {
                return $this->json($row[$key]);
            }
            return $this->json($row);
        } elseif ('ttsmotions' == $table) {

            $sql = sprintf(
                "SELECT "
                . "tts_key,date,source,sitting_id,chair,motion_type,summary,resolution,progress,topic,category,tags,bill_refs,memo,agencies,speakers"
                . " FROM ttsmotions "
                . " WHERE %s"
                , $this->getWhere(json_decode($_GET['q']))
            );
            $res = $db->query($sql);

            $obj = new StdClass;
            $obj->paging = array('count' => 0);
            $obj->entries  = array();
            while ($row = $res->fetch_assoc()) {
                $obj->entries[] = $row;
            }
            $obj->paging['count'] = count($obj->entries);
            return $this->json($obj);
        } elseif ('laws' == $table) {

            $sql = sprintf(
                "SELECT * FROM laws WHERE %s",
                $this->getWhere(json_decode($_GET['q']))
            );
            $res = $db->query($sql);
            $obj = new StdClass;
            $obj->paging = array('count' => 0);
            $obj->entries  = array();
            while ($row = $res->fetch_assoc()) {
                $obj->entries[] = $row;
            }
            $obj->paging['count'] = count($obj->entries);
            return $this->json($obj);
        } elseif ('bills' == $table) {

            $sql = sprintf(
                "SELECT data, doc"
                . ",bill_id,bill_ref,summary,proposed_by,abstract,report_of,reconsideration_of,bill_type,sitting_introduced"
                . ", ARRAY_TO_JSON(case when ttsbills.sponsors is null then bills.sponsors else ttsbills.sponsors end) AS sponsors"
                . ", ARRAY_TO_JSON((case when ttsbills.cosponsors is null then bills.cosponsors else ttsbills.cosponsors end)) as cosponsors"
                . " FROM bills LEFT JOIN (select sponsors, cosponsors, bill_ref, sitting_introduced, 'legislative'::text as bill_type from ttsbills) ttsbills USING (bill_ref) "
                . " WHERE %s",
                $this->getWhere(json_decode($_GET['q']))
            );
            $res = $db->query($sql);
            $obj = new StdClass;
            $obj->paging = array('count' => 0);
            $obj->entries  = array();
            while ($row = $res->fetch_assoc()) {
                $row = $this->getBill($row);
                $obj->entries[] = $row;
            }
            $obj->paging['count'] = count($obj->entries);
            if ($_GET['fo']) {
                if (!$obj->entries[0]) {
                    header("HTTP/1.0 404 Not Found"); 
                    header('Access-Control-Allow-Origin: *');
                    header('Access-Control-Allow-Methods: GET');
                    return $this->json(null);
                }
                return $this->json($obj->entries[0]);
            }
            return $this->json($obj);
        }
    }
}
