<?php

namespace App\Filament\Resources\CoachSessies\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CoachSessieInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sessie')
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Lid'),
                        TextEntry::make('date')
                            ->label('Datum')
                            ->date('d-m-Y'),
                        TextEntry::make('range_name')
                            ->label('Baan'),
                        TextEntry::make('manual_reflection')
                            ->label('Reflectie van het lid')
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Section::make('AI-reflectie')
                    ->schema([
                        TextEntry::make('aiReflection.summary')
                            ->label('Samenvatting')
                            ->columnSpanFull()
                            ->placeholder('Geen AI-reflectie beschikbaar'),
                        TextEntry::make('aiReflection.next_focus')
                            ->label('Volgende focus')
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
