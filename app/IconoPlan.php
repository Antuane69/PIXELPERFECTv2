<?php

namespace App;

enum IconoPlan: string
{
    case Crown = 'CrownOutlined';
    case Rocket = 'RocketOutlined';
    case Star = 'StarOutlined';
    case Thunderbolt = 'ThunderboltOutlined';
    case Team = 'TeamOutlined';
    case SafetyCertificate = 'SafetyCertificateOutlined';
    case Trophy = 'TrophyOutlined';
    case Fire = 'FireOutlined';
    case Heart = 'HeartOutlined';
    case Smile = 'SmileOutlined';
    case Bulb = 'BulbOutlined';
    case Global = 'GlobalOutlined';
    case Cloud = 'CloudOutlined';
    case Database = 'DatabaseOutlined';
    case Shop = 'ShopOutlined';
    case Bank = 'BankOutlined';
    case Apartment = 'ApartmentOutlined';
    case Appstore = 'AppstoreOutlined';
    case Tool = 'ToolOutlined';
    case Experiment = 'ExperimentOutlined';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
