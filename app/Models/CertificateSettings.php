<?php
require_once 'config/database.php';
require_once 'config/helpers.php';
class CertificateSettings
{
    private $db;
    public function __construct() { $this->db=Database::connect(); }
    public function get($eventId)
    {
        $q=$this->db->prepare('SELECT * FROM event_certificate_settings WHERE event_id=?');$q->execute([$eventId]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: ['organization'=>'Campus Chronicle','signatory'=>'Event Coordinator','signatory_title'=>'Organizing Committee','participation_template_id'=>null,'winner_template_id'=>null];
    }
    public function templates()
    {
        return $this->db->query("SELECT id,name,file_path FROM content_templates WHERE template_type='certificate' AND status='Published' AND mime_type IN ('image/jpeg','image/png','image/webp') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function save($eventId, $input)
    {
        $q=$this->db->prepare('SELECT id FROM events WHERE id=?');$q->execute([$eventId]);
        if (!$q->fetchColumn()) throw new InvalidArgumentException('Event not found.');
        $values=[$eventId];
        foreach (['organization','signatory','signatory_title'] as $field) {
            $value=trim($input[$field] ?? '');
            if ($value==='' || mb_strlen($value)>150) throw new InvalidArgumentException('Organization and signatory fields must contain 1–150 characters.');
            $values[]=$value;
        }
        $valid=array_column($this->templates(),'id');
        foreach (['participation_template_id','winner_template_id'] as $field) {
            $id=(int)($input[$field] ?? 0);
            if ($id && !in_array($id,$valid)) throw new InvalidArgumentException('Choose a published certificate image template.');
            $values[]=$id ?: null;
        }
        $q=$this->db->prepare('INSERT INTO event_certificate_settings(event_id,organization,signatory,signatory_title,participation_template_id,winner_template_id) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE organization=VALUES(organization),signatory=VALUES(signatory),signatory_title=VALUES(signatory_title),participation_template_id=VALUES(participation_template_id),winner_template_id=VALUES(winner_template_id)');$q->execute($values);
    }
    public function presentation($eventId, $type)
    {
        $settings=$this->get($eventId);
        $settings['background']='';
        $id=$settings[$type==='Winner'?'winner_template_id':'participation_template_id'];
        if ($id) {
            $q=$this->db->prepare("SELECT file_path FROM content_templates WHERE id=? AND template_type='certificate' AND status='Published' AND mime_type IN ('image/jpeg','image/png','image/webp')");$q->execute([$id]);$path=$q->fetchColumn();
            if ($path && cc_asset_exists($path)) $settings['background']=cc_normalize_asset_path($path);
        }
        return $settings;
    }
}
