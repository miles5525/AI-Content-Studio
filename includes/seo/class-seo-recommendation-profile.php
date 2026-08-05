<?php
if(!defined('ABSPATH')){exit;}
final class AICS_SEO_Recommendation_Profile{
	private const PROFILES=array('native'=>array('label'=>'Native WordPress','title_min'=>30,'title_max'=>60,'description_min'=>120,'description_max'=>160,'slug_max'=>75,'note'=>'Character-based recommendations may differ from visual-width tools.'),'yoast'=>array('label'=>'Yoast SEO','title_min'=>30,'title_max'=>60,'description_min'=>120,'description_max'=>156,'slug_max'=>75,'note'=>'Yoast may calculate pixel width and scoring differently.'),'rank_math'=>array('label'=>'Rank Math','title_min'=>30,'title_max'=>60,'description_min'=>120,'description_max'=>160,'slug_max'=>75,'note'=>'Rank Math scoring may differ from this deterministic analysis.'),'aioseo'=>array('label'=>'All in One SEO','title_min'=>30,'title_max'=>60,'description_min'=>120,'description_max'=>160,'slug_max'=>75,'note'=>'AIOSEO scoring may differ from this deterministic analysis.'));
	public static function get(string $key):array{return self::PROFILES[$key]??self::PROFILES['native'];}
}
