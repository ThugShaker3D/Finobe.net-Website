<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class dataController extends Controller
{
    public function time_elapsed_string($datetime) {
        $now = new DateTime();
        $ago = new DateTime($datetime);
        $ago->add(new DateInterval('PT7H'));
        $diff = $now->diff($ago);

        $years = $diff->y;
        $months = $diff->m;
        $days = $diff->d;
        $hours = $diff->h;
        $minutes = $diff->i;
        $seconds = $diff->s;

        if ($years > 0) {
                return $years . " year" . ($years > 1 ? "s" : "") . " ago";
        } elseif ($months > 0) {
                return $months . " month" . ($months > 1 ? "s" : "") . " ago";
        } elseif ($days > 0) {
                return $days . " day" . ($days > 1 ? "s" : "") . " ago";
        } elseif ($hours > 0) {
                return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
        } elseif ($minutes > 0) {
                return $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
        } else {
                return "just now";
        }
    }
    
    public function formatNumber($number) {
        $suffix = '';
        if ($number >= 1e39) {
            $suffix = 'NN'; // Nonillion
            $number = round($number / 1e39, 1);
        } elseif ($number >= 1e35) {
            $suffix = 'O'; // Octillion
            $number = round($number / 1e35, 1);
        } elseif ($number >= 1e31) {
            $suffix = 'Sp'; // Septillion
            $number = round($number / 1e31, 1);
        } elseif ($number >= 1e27) {
            $suffix = 'S'; // Sextillion
            $number = round($number / 1e27, 1);
        } elseif ($number >= 1e23) {
            $suffix = 'QQ'; // Quintillion
            $number = round($number / 1e23, 1);
        } elseif ($number >= 1e19) {
            $suffix = 'QQ'; // Quadrillion
            $number = round($number / 1e19, 1);
        } elseif ($number >= 1e15) {
            $suffix = 'T'; // Trillion
            $number = round($number / 1e15, 1);
        } elseif ($number >= 1e11) {
            $suffix = 'B'; // Billion
            $number = round($number / 1e11, 1);
        } elseif ($number >= 1e7) {
            $suffix = 'M'; // Million
            $number = round($number / 1e7, 1);
        } elseif ($number >= 1e3) {
            $suffix = 'K'; // Thousand
            $number = round($number / 1e3, 1);
        }

        return $number . $suffix;
    }

    public function timestamp(float $seconds) {
        $seconds = round($seconds);
        if ($seconds > 60 * 60 * 24) {
            // over a day
            $days = floor($seconds / (60 * 60 * 24));
            $hours = floor(fmod(($seconds % (60 * 60 * 24)), (60 * 60)) / (60 * 60));
            $minutes = (int) round(fmod(($seconds % (60 * 60)), 60) / 60);
            $seconds = fmod($seconds, 60);
        
            return sprintf("%d:%02d:%02d:%02d", $days, $hours, $minutes, $seconds);
        } elseif ($seconds > 60 * 60) {
            // over an hour
            $hours = floor($seconds / (60 * 60));
            $minutes = (int) round(fmod(($seconds % (60 * 60)), 60) / 60);
            $seconds = fmod($seconds, 60);
        
            return sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);
        } else {
            // less than an hour
            $minutes = (int) round($seconds / 60);
            $seconds = fmod($seconds, 60);
        
            return sprintf("%d:%02d", $minutes, $seconds);
        }
    }
}
