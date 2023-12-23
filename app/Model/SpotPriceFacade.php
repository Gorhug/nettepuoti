<?php
namespace App\Model;

use Nette;

final class SpotPriceFacade
{
    public function __construct(
        private Nette\Database\Explorer $database,
        private \App\Settings $settings,
    ) {
    }

    private static function p_format(\DateTimeImmutable $date)
    {
        return $date->format("YmdHi");
    }
    private function fetchPrices(\DateTimeImmutable $date)
    {

        $token = $this->settings->entsoeToken;
        $entsoe_url = "https://web-api.tp.entsoe.eu/api?securityToken=$token&documentType=A44&in_Domain=10YFI-1--------U&out_Domain=10YFI-1--------U";
        $tz_utc = new \DateTimeZone("UTC");
        $today = $date->setTimezone(new \DateTimeZone("Europe/Stockholm"));
        // aloitus keskipäivästä, jotta kesäaikasiirtymät onnistuvat oikein
        // +- 1 päivä (24h) ei välttämättä osu keskiyöhön silloin
        $midday = $today->setTime(12, 0);
        $i_day = new \DateInterval("P1D");
        $i_days = new \DateInterval("P2D");
        $d_start = $midday->sub($i_day)->setTime(0, 0)->setTimezone($tz_utc);
        $d_end = $midday->add($i_days)->setTime(0, 0)->setTimezone($tz_utc);
        $p_start = $this->p_format($d_start);
        $p_end = $this->p_format($d_end);
        $api_call = "$entsoe_url&periodStart=$p_start&periodEnd=$p_end";
        // print_r($api_call);
        // exit();

        $ch = curl_init($api_call);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            exit('Error:' . curl_error($ch));
        }
        // print_r($result);
        $parsed = new \SimpleXMLElement($result);
        return $parsed;
    }
    public function updateSpotPrices()
    {

        $i_day = new \DateInterval("P1D");
        $tz_utc = new \DateTimeZone("UTC");
        // $_GET API mahdolliseksi joskus?
        // $q_date = '2023-03-25 17:00:00+02';

        $q_date = 'now';
        // echo '<pre>';
        $time_fi = new \DateTimeImmutable($q_date, new \DateTimeZone("Europe/Helsinki"));
        $midnight = $time_fi->setTime(0, 0)->setTimezone($tz_utc);
        $dayend = $time_fi->setTime(23, 0)->setTimezone($tz_utc);
        $nextday = $time_fi->add($i_day)->setTime(23, 0)->setTimezone($tz_utc);
        $i_hour = new \DateInterval("PT1H");
        // HUOM: TESTEJÄ VARTEN
        $nextday = $nextday->add($i_hour);
        $price_release = $time_fi->setTime(14, 30);
        $hours = [$midnight, $dayend];


        if ($time_fi > $price_release) {
            $hours[] = $nextday;
        }
        $atomize = function (\DateTimeImmutable $q) 
        {
            return date_format($q, DATE_ATOM);
        };
        $q_hours = array_map($atomize, $hours);

        // if !has midnight, midday, end of day tomorrow
        if (
            $this->database->query('SELECT COUNT(*) FROM price WHERE hour IN ?', $q_hours)->fetchField()
            
             != count($q_hours)
        ) {

            $entsoe = $this->fetchPrices($time_fi);
            $amount = 'price.amount';
            $new = [];
            foreach ($entsoe->TimeSeries as $ts) {
                $time = new \DateTimeImmutable($ts->Period->timeInterval->start);
                foreach ($ts->Period->Point as $point) {
                    $new[] = [
                        'hour' => $time->format(DATE_ATOM),
                        'euro_mwh' => (string) $point->$amount
                    ];
                    $time = $time->add($i_hour);
                }
            }
            // var_dump($new);
            if ($new) {
                try {
                    $result = $this->database->query('INSERT INTO price ? ON CONFLICT DO NOTHING', $new);
                    // echo 'Inserted ' .  $result->getRowCount() . " rows\n";
                } catch (\Exception $e) {
                    error_log($e->getMessage());
                }
            }

            // var_dump($database->log());

        }


        // $this->database->table('spot_prices')->insert($data);
    }

    public function getSpotPrices()
    {
        $i_hour = new \DateInterval("PT1H");
        $now = new \DateTimeImmutable();
        $start = $now->sub($i_hour);
        return $this->database
            ->table('price')
            ->where('hour > ', $start->format(DATE_ATOM))
            ->order('hour ASC');
    }
}
