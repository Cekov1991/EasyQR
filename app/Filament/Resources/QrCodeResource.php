<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QrCodeResource\Pages;
use App\Filament\Resources\QrCodeResource\RelationManagers\ScansRelationManager;
use App\Models\QrCode;
use App\Models\User;
use App\Rules\ValidQrUrl;
use App\Support\SubscriptionPrice;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class QrCodeResource extends Resource
{
    protected static ?string $model = QrCode::class;

    protected static ?string $navigationIcon = 'heroicon-m-qr-code';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Select::make('type')
                                    ->options(fn (?QrCode $record): array => static::typeOptions($record))
                                    ->default('static')
                                    ->required()
                                    ->disabled(fn ($record) => $record !== null)
                                    ->dehydrated()
                                    ->helperText(fn (?QrCode $record): ?string => $record === null
                                        ? static::dynamicUnavailableReason()
                                        : null),
                            ]),
                    ]),

                Section::make('QR Code Type')
                    ->schema([
                        Forms\Components\Select::make('qr_content_type')
                            ->label('QR Code Type')
                            ->options(QrCode::QR_CONTENT_TYPES)
                            ->default('website')
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('qr_content_data', []))
                            ->disabled(fn ($record) => $record?->type === 'static'),
                    ])
                    ->visible(fn ($record) => $record?->type !== 'static'),

                // All content sections - only visible/enabled for dynamic QR codes or new records
                Section::make('Website Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('qr_content_data.url')
                            ->label('Website URL')
                            ->required()
                            ->url()
                            ->rules([new ValidQrUrl])
                            ->placeholder('https://example.com'),
                    ])
                    ->visible(
                        fn (Forms\Get $get, $record): bool => $get('qr_content_type') === 'website' &&
                            ($record === null || $record->type === 'dynamic')
                    ),

                Section::make('Wi-Fi Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('qr_content_data.ssid')
                            ->label('Network Name (SSID)')
                            ->required(),
                        Forms\Components\Select::make('qr_content_data.security')
                            ->label('Security Type')
                            ->options([
                                'WPA' => 'WPA/WPA2',
                                'WEP' => 'WEP',
                                'nopass' => 'No Security',
                            ])
                            ->default('WPA')
                            ->required(),
                        Forms\Components\TextInput::make('qr_content_data.password')
                            ->label('Password')
                            ->password()
                            ->visible(fn (Forms\Get $get): bool => $get('qr_content_data.security') !== 'nopass'),
                        Forms\Components\Toggle::make('qr_content_data.hidden')
                            ->label('Hidden Network')
                            ->default(false),
                    ])
                    ->visible(
                        fn (Forms\Get $get, $record): bool => $get('qr_content_type') === 'wifi' &&
                            ($record === null || $record->type === 'dynamic')
                    ),

                Section::make('Email Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('qr_content_data.email')
                            ->label('Email Address')
                            ->email()
                            ->required(),
                        Forms\Components\TextInput::make('qr_content_data.subject')
                            ->label('Subject')
                            ->placeholder('Optional'),
                        Forms\Components\Textarea::make('qr_content_data.body')
                            ->label('Email Body')
                            ->placeholder('Optional'),
                    ])
                    ->visible(
                        fn (Forms\Get $get, $record): bool => $get('qr_content_type') === 'email' &&
                            ($record === null || $record->type === 'dynamic')
                    ),

                Section::make('WhatsApp Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('qr_content_data.phone')
                            ->label('Phone Number')
                            ->required()
                            ->placeholder('1234567890 (include country code, no + or spaces)'),
                        Forms\Components\Textarea::make('qr_content_data.message')
                            ->label('Pre-filled Message')
                            ->placeholder('Optional'),
                    ])
                    ->visible(
                        fn (Forms\Get $get, $record): bool => $get('qr_content_type') === 'whatsapp' &&
                            ($record === null || $record->type === 'dynamic')
                    ),

                Section::make('Contact (vCard) Configuration')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('qr_content_data.first_name')
                                    ->label('First Name')
                                    ->required(),
                                Forms\Components\TextInput::make('qr_content_data.last_name')
                                    ->label('Last Name')
                                    ->required(),
                                Forms\Components\TextInput::make('qr_content_data.organization')
                                    ->label('Organization'),
                                Forms\Components\TextInput::make('qr_content_data.title')
                                    ->label('Job Title'),
                                Forms\Components\TextInput::make('qr_content_data.phone')
                                    ->label('Phone Number')
                                    ->tel(),
                                Forms\Components\TextInput::make('qr_content_data.email')
                                    ->label('Email')
                                    ->email(),
                            ]),
                        Forms\Components\TextInput::make('qr_content_data.website')
                            ->label('Website')
                            ->url()
                            ->columnSpanFull(),
                    ])
                    ->visible(
                        fn (Forms\Get $get, $record): bool => $get('qr_content_type') === 'vcard' &&
                            ($record === null || $record->type === 'dynamic')
                    ),

                Section::make('SMS Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('qr_content_data.phone')
                            ->label('Phone Number')
                            ->required()
                            ->placeholder('+1234567890'),
                        Forms\Components\Textarea::make('qr_content_data.message')
                            ->label('Pre-filled Message')
                            ->placeholder('Optional'),
                    ])
                    ->visible(
                        fn (Forms\Get $get, $record): bool => $get('qr_content_type') === 'sms' &&
                            ($record === null || $record->type === 'dynamic')
                    ),

                Section::make('Phone Call Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('qr_content_data.phone')
                            ->label('Phone Number')
                            ->required()
                            ->placeholder('+1234567890'),
                    ])
                    ->visible(
                        fn (Forms\Get $get, $record): bool => $get('qr_content_type') === 'phone' &&
                            ($record === null || $record->type === 'dynamic')
                    ),

                Section::make('Text Configuration')
                    ->schema([
                        Forms\Components\Textarea::make('qr_content_data.text')
                            ->label('Text Content')
                            ->required()
                            ->placeholder('Enter the text to display'),
                    ])
                    ->visible(
                        fn (Forms\Get $get, $record): bool => $get('qr_content_type') === 'text' &&
                            ($record === null || $record->type === 'dynamic')
                    ),

                Section::make('Calendar Event Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('qr_content_data.summary')
                            ->label('Event Title')
                            ->required(),
                        Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('qr_content_data.start_date')
                                    ->label('Start Date & Time')
                                    ->required()
                                    ->format('Ymd\THis\Z'),
                                Forms\Components\DateTimePicker::make('qr_content_data.end_date')
                                    ->label('End Date & Time')
                                    ->required()
                                    ->format('Ymd\THis\Z'),
                            ]),
                        Forms\Components\TextInput::make('qr_content_data.location')
                            ->label('Location'),
                        Forms\Components\Textarea::make('qr_content_data.description')
                            ->label('Description'),
                    ])
                    ->visible(
                        fn (Forms\Get $get, $record): bool => $get('qr_content_type') === 'calendar' &&
                            ($record === null || $record->type === 'dynamic')
                    ),

                Section::make('Location Configuration')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('qr_content_data.latitude')
                                    ->label('Latitude')
                                    ->required()
                                    ->numeric()
                                    ->placeholder('37.7749'),
                                Forms\Components\TextInput::make('qr_content_data.longitude')
                                    ->label('Longitude')
                                    ->required()
                                    ->numeric()
                                    ->placeholder('-122.4194'),
                            ]),
                    ])
                    ->visible(
                        fn (Forms\Get $get, $record): bool => $get('qr_content_type') === 'location' &&
                            ($record === null || $record->type === 'dynamic')
                    ),

                // QR Code appearance - only for new records (both types can customize during creation)
                Section::make('QR Code Appearance')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Radio::make('options.style')
                                    ->label('Style')
                                    ->options(QrCode::QR_STYLES)
                                    ->default(QrCode::DEFAULT_STYLE)
                                    ->required()
                                    ->view('filament.forms.components.qr-style-picker')
                                    ->helperText('Shape of the dots and corner squares. All styles scan the same.')
                                    ->columnSpanFull(),

                                Forms\Components\FileUpload::make('options.logo_path')
                                    ->label('Centre logo')
                                    ->image()
                                    ->live()
                                    ->disk(config('filesystems.default'))
                                    ->directory('qr-logos')
                                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                                    ->maxSize(2048)
                                    ->helperText('Optional. A logo covers part of the code, so it forces PNG output and the highest error correction.')
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('options.format')
                                    ->label('Image Format')
                                    ->options([
                                        'png' => 'PNG',
                                        'svg' => 'SVG',
                                        'eps' => 'EPS',
                                    ])
                                    ->default('png')
                                    ->required()
                                    ->disabled(fn (Forms\Get $get): bool => filled($get('options.logo_path')))
                                    ->dehydrated()
                                    ->dehydrateStateUsing(fn ($state, Forms\Get $get) => filled($get('options.logo_path')) ? 'png' : $state)
                                    ->helperText(fn (Forms\Get $get): ?string => filled($get('options.logo_path'))
                                        ? 'A centre logo can only be composited onto a PNG.'
                                        : null),

                                Forms\Components\ColorPicker::make('options.color')
                                    ->label('QR Code Color')
                                    ->default('#000000'),

                                Forms\Components\Select::make('options.errorCorrection')
                                    ->label('Error Correction')
                                    ->options([
                                        'L' => 'Low (7%)',
                                        'M' => 'Medium (15%)',
                                        'Q' => 'Quartile (25%)',
                                        'H' => 'High (30%)',
                                    ])
                                    ->default('M')
                                    ->disabled(fn (Forms\Get $get): bool => filled($get('options.logo_path')))
                                    ->dehydrated()
                                    ->dehydrateStateUsing(fn ($state, Forms\Get $get) => filled($get('options.logo_path')) ? 'H' : $state)
                                    ->helperText(fn (Forms\Get $get): ?string => filled($get('options.logo_path'))
                                        ? 'A covered centre needs the highest error correction to stay scannable.'
                                        : null),

                                Forms\Components\TextInput::make('options.size')
                                    ->label('Size (px)')
                                    ->numeric()
                                    ->default(300)
                                    ->minValue(100)
                                    ->maxValue(2000),
                            ]),
                    ])
                    ->visible(fn ($record) => $record === null)
                    ->description('QR code appearance cannot be changed after creation to preserve printed codes.'),

                // Show current settings as read-only for existing records
                Section::make('Current QR Code Settings')
                    ->schema([
                        Forms\Components\Placeholder::make('qr_type_display')
                            ->label('QR Code Type')
                            ->content(fn ($record) => $record ? QrCode::QR_CONTENT_TYPES[$record->qr_content_type] ?? ucfirst($record->qr_content_type) : ''),
                        Forms\Components\Placeholder::make('style_display')
                            ->label('Style')
                            ->content(fn ($record) => $record ? (QrCode::QR_STYLES[$record->options['style'] ?? QrCode::DEFAULT_STYLE] ?? 'Rounded') : ''),
                        Forms\Components\Placeholder::make('format_display')
                            ->label('Format')
                            ->content(fn ($record) => $record ? strtoupper($record->options['format'] ?? 'PNG') : ''),
                        Forms\Components\Placeholder::make('color_display')
                            ->label('Color')
                            ->content(fn ($record) => $record ? ($record->options['color'] ?? '#000000') : ''),
                        Forms\Components\Placeholder::make('size_display')
                            ->label('Size')
                            ->content(fn ($record) => $record ? ($record->options['size'] ?? '300').'px' : ''),
                        Forms\Components\Placeholder::make('logo_display')
                            ->label('Centre Logo')
                            ->content(fn ($record) => $record && filled($record->options['logo_path'] ?? null)
                                ? basename($record->options['logo_path'])
                                : 'None'),
                    ])
                    ->visible(fn ($record) => $record !== null)
                    ->description(
                        fn ($record) => $record?->type === 'static'
                            ? 'Static QR codes cannot be modified after creation to preserve printed codes.'
                            : 'QR code appearance cannot be changed after creation to preserve printed codes.'
                    ),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('qr_code_image')
                    ->label('QR Code')
                    ->square(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('qr_content_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        QrCode::QR_CONTENT_TYPES[$state] => $state,
                        default => ucfirst($state),
                    })
                    ->colors([
                        'primary' => 'website',
                        'success' => 'wifi',
                        'warning' => 'email',
                        'info' => 'whatsapp',
                        'secondary' => 'vcard',
                        'danger' => 'sms',
                        'gray' => 'phone',
                        'indigo' => 'text',
                        'pink' => 'calendar',
                        'emerald' => 'location',
                    ]),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'danger' => 'static',
                        'success' => 'dynamic',
                    ]),
                Tables\Columns\TextColumn::make('scan_count')
                    ->label('Scans')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('qr_content_type')
                    ->label('QR Content Type')
                    ->options(QrCode::QR_CONTENT_TYPES),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'static' => 'Static',
                        'dynamic' => 'Dynamic',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                if (Auth::check()) {
                    return $query->where('user_id', Auth::id());
                }
            });
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (Auth::check()) {
            $query->where('user_id', Auth::id());
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            ScansRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQrCodes::route('/'),
            'create' => Pages\CreateQrCode::route('/create'),
            'create-from-session' => Pages\CreateFromSession::route('/create-from-session'),
            'view' => Pages\ViewQrCode::route('/{record}'),
            'edit' => Pages\EditQrCode::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $user->quota()->canCreate('static')
            || $user->quota()->canCreate('dynamic');
    }

    /**
     * Existing records always list both types so a disabled select still
     * renders its current value — a lapsed owner editing a dynamic code must
     * not see the field blank.
     *
     * @return array<string, string>
     */
    public static function typeOptions(?QrCode $record = null): array
    {
        $options = ['static' => 'Static QR Code'];

        if ($record !== null || Auth::user()?->quota()->canCreate('dynamic')) {
            $options['dynamic'] = 'Dynamic QR Code';
        }

        return $options;
    }

    /**
     * Why the dynamic option is missing, phrased for the person who expected it.
     */
    public static function dynamicUnavailableReason(): ?string
    {
        $user = Auth::user();

        if ($user === null || $user->quota()->canCreate('dynamic')) {
            return null;
        }

        if ($user->isLapsed()) {
            return 'Dynamic QR codes need an active subscription. Your static QR codes are unaffected.';
        }

        return self::dynamicLimitReachedMessage($user);
    }

    /**
     * The full-quota notice, with the larger plan offered to anyone it would
     * actually raise. An account already at or above that ceiling has nothing
     * to buy, so it is told only how to free a slot.
     */
    public static function dynamicLimitReachedMessage(User $user): string
    {
        $limit = $user->quota()->limitFor('dynamic');
        $message = sprintf('You have used all %d of your dynamic QR codes. Delete one to free a slot', $limit);

        if ($limit >= (int) config('subscription.larger_quota.dynamic')) {
            return $message.'.';
        }

        return sprintf(
            '%s, or move to the larger plan: %s. Email %s to get it.',
            $message,
            SubscriptionPrice::largerQuotaOffer(),
            config('site.support_email'),
        );
    }
}
