<?php

namespace Eloquent;

class Church extends \Illuminate\Database\Eloquent\Model {

    protected $table = 'templomok';
    protected $appends = array('fullName', 'responsible', 'lon', 'osm');

    public function getResponsibleAttribute($value) {
        return array($this->letrehozta);
    }

    public function getWriteAccessAttribute($value) {
        global $user;
        return $this->McheckWriteAccess($user);
    }

    public function photos() {
        return $this->hasMany('\Eloquent\Photo')->ordered();
    }

    public function osms() {
        return $this->belongsToMany('\Eloquent\OSM', 'lookup_church_osm', 'church_id', 'osm_id');
    }

    public function keywordshortcuts() {
        return $this->hasMany('\Eloquent\KeywordShortcut');
    }

    public function getOsmAttribute() {
        if ($this->osms->first()->enclosing AND count($this->osms->first()->enclosing->toArray()) < 1) {
            $overpass = new \OverpassApi();
            $overpass->updateEnclosing($this->osms->first());
            $this->load(osms);
        }
        return $this->osms->first();
    }

    public function remarks() {
        return $this->hasMany('\Eloquent\Remark')->orderBy('created_at', 'DESC');
    }

    public function neighbours() {
        return $this->hasMany('\Eloquent\Distance', 'from', 'id')->orderBy('distance', 'ASC');
    }

    public function closestNeighbour() {
        return $this->neighbours()->take(1);
    }

    public function neighbourWithinDistance($distance = 10000) {
        return $this->neighbours()->where("distance", "<=", $distance);
    }

    function getFullNameAttribute($value) {
        $return = $this->nev;
        if (!empty($this->ismertnev)) {
            $return .= ' (' . $this->ismertnev . ')';
        } else {
            $return .= ' (' . $this->varos . ')';
        }
        return $return;
    }

    function getLonAttribute($value) {
        return $this->lng;
    }

    function getRemarksStatusAttribute($value) {
        $remark = $this->remarks()
                        ->select('allapot')
                        ->groupBy('allapot')
                        ->orderByRaw("FIND_IN_SET(allapot, 'u,f,j')")->first();

        if (!$remark) {
            $return['html'] = "<span class='alap'>(nincs)</span>";
            $return['text'] = "Nincsenek észrevételek";
            $return['mark'] = false;
        } else if ($remark->allapot == 'u') {
            $return['html'] = "<a href=\"javascript:OpenScrollWindow('/templom/$this->id/eszrevetelek',550,500);\"><img src=/img/csomag.gif title='Új észrevételt írtak hozzá!' align=absmiddle border=0></a> ";
            $return['text'] = "Új észrevételt írtak hozzá!";
            $return['mark'] = 'u';
        } else if ($remark->allapot == 'f') {
            $return['html'] = "<a href=\"javascript:OpenScrollWindow('/templom/$this->id/eszrevetelek',550,500);\"><img src=/img/csomagf.gif title='Észrevétel javítása folyamatban!' align=absmiddle border=0></a> ";
            $return['text'] = "Észrevétel javítása folyamatban!";
            $return['mark'] = 'f';
        } else if ($remark->allapot == 'j') {
            $return['html'] = "<a href=\"javascript:OpenScrollWindow('/templom/$this->id/eszrevetelek',550,500);\"><img src=/img/csomag1.gif title='Észrevételek' align=absmiddle border=0></a> ";
            $return['text'] = "Észrevételek";
            $return['mark'] = 'j';
        }
        return $return;
    }

    public function delete() {
        $this->neighbours()->delete();
        Distance::where('to', $this->id)->delete();
        $this->remarks()->delete();
        $this->photos()->delete();
        parent::delete();
    }

    public function MgetLocation() {
        return;
        $this->location = new \Location();
        $this->location->getByChurchId($this->id);
        $this->osm->religiousAdministration;
        $this->location->country = $this->osm->country;
        $this->location->county = $this->osm->county;
        $this->location->city = $this->osm->city;

        if (isset($this->osm) AND $this->osm != '') {
            $this->location->lat = $this->osm->lat;
            $this->location->lon = $this->osm->lon;
            $this->location->osm = $this->osm->osmtype . "/" . $this->osm->osmid;
        } else {
            $this->location->lon = $this->location->lng;
            unset($this->location->lng);
        }
    }

    public function MgetReligious_administration() {
        $this->religious_administration = new \stdClass();
        $this->religious_administration->diocese = new \Diocese();
        $this->religious_administration->diocese->getByChurchId($this->id);
        $this->religious_administration->deaconry = new \Deaconry();
        $this->religious_administration->deaconry->getByChurchId($this->id);
        $this->MgetParish();
    }

    function MgetParish() {
        if (!isset($this->religious_administration)) {
            $this->religious_administration = new \stdClass();
        }
        $parish = new \Parish();
        $parish->getByChurchId($this->id);
        $this->religious_administration->parish = $parish;
    }

    function McheckReadAccess($user) {
        if ($this->ok == 'i')
            return true;
        if ($this->letrehozta == $user->username)
            return true;
        if ($user->checkRole('miserend'))
            return true;
        return false;
    }

    function McheckWriteAccess($user) {
        if ($user->checkRole('miserend'))
            return true;
        if ($this->letrehozta == $user->username)
            return true;
        if (!is_array($user->responsible))
            return false;
        if (in_array($this->id, $user->responsible['church']))
            return true;
        if (in_array($this->MgetDioceseId(), $user->responsible['diocese']))
            return true;

        return false;
    }

    function MgetDioceseId() {
        return $this->religious_administration->diocese->id;
    }

    function scopeWhereShortcutLike($query, $keyword, $type) {
        return $query->whereHas('keywordshortcuts', function ($query) use ($keyword, $type) {
                    $query->where('type', $type)->where('value', 'like', $keyword);
                });
    }

    public function scopeWhereHasTag($query, $name, $value) {
        if (is_array($name)) {
            $nameOperator = $name[0];
            $nameValue = $name[1];
        } else {
            $nameValue = $name;
            $nameOperator = '=';
        }
        if (is_array($value)) {
            $valueOperator = $value[0];
            $valueValue = $value[1];
        } else {
            $valueValue = $value;
            $valueOperator = '=';
        }
        return $query->whereHas('tags', function ($query) use ( $nameOperator, $nameValue, $valueOperator, $valueValue ) {
                    $query->where('name', $nameOperator, $nameValue)->where('value', $valueOperator, $valueValue);
                });
    }

}
