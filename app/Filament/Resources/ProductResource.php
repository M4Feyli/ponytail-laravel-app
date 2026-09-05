<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Varor';

    protected static ?string $modelLabel = 'vara';

    protected static ?string $pluralModelLabel = 'varor';

    /** Lets the panel's globala sök hitta varor via namn eller SKU. */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Grunduppgifter')
                ->columns(2)
                ->components([
                    Select::make('category_id')
                        ->label('Kategori')
                        ->relationship('category', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),

                    TextInput::make('sku')
                        ->label('SKU / artikelnummer')
                        ->required()
                        ->unique(ignoreRecord: true),

                    TextInput::make('name')
                        ->label('Namn')
                        ->required()
                        ->columnSpanFull(),

                    TextInput::make('condition')
                        ->label('Skick')
                        ->datalist(array_values(Product::CONDITION_PRESETS))
                        ->default('Mycket bra skick')
                        ->helperText('Fritext -- skriv valfritt, eller välj ett vanligt förslag.'),

                    Textarea::make('description')
                        ->label('Beskrivning')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            Section::make('Pris & lager')
                ->columns(3)
                ->components([
                    TextInput::make('price')
                        ->label('Pris')
                        ->numeric()
                        ->required()
                        ->prefix('kr')
                        ->minValue(0),

                    TextInput::make('sale_price')
                        ->label('Reapris')
                        ->numeric()
                        ->nullable()
                        ->prefix('kr')
                        ->minValue(0)
                        ->helperText('Lämna tomt om varan inte är nedsatt.'),

                    Grid::make(1)
                        ->components([
                            Toggle::make('is_new')
                                ->label('Ny inleverans')
                                ->helperText('Visas med "Nyhet"-märkning i butiken.'),

                            Toggle::make('is_active')
                                ->label('Publicerad')
                                ->default(true)
                                ->helperText('Av = dold från butiken.'),
                        ]),
                ]),

            Section::make('Bild')
                ->components([
                    FileUpload::make('image')
                        ->label('Produktbild')
                        ->disk('public')
                        ->directory('products')
                        ->image()
                        ->imageEditor()
                        ->hiddenLabel(false),
                ]),

            Section::make('Storlekar & lagersaldo')
                ->description('En rad per storlek. Begagnade varor har oftast bara 1 st per storlek.')
                ->components([
                    Repeater::make('variants')
                        ->label('Storlekar')
                        ->relationship()
                        ->schema([
                            TextInput::make('size')
                                ->label('Storlek')
                                ->required(),

                            TextInput::make('stock')
                                ->label('Antal i lager')
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->minValue(0),
                        ])
                        ->columns(2)
                        ->addActionLabel('Lägg till storlek')
                        ->defaultItems(1)
                        ->reorderable(false),
                ]),

            Section::make('Specifikationer')
                ->description('Fritt fält för material, märke, mått m.m. -- varierar per kategori.')
                ->collapsed()
                ->components([
                    KeyValue::make('specs')
                        ->label('Specifikationer')
                        ->keyLabel('Egenskap')
                        ->valueLabel('Värde')
                        ->reorderable()
                        ->addActionLabel('Lägg till specifikation')
                        ->hiddenLabel(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Bild')
                    ->disk('public'),

                TextColumn::make('name')
                    ->label('Namn')
                    ->searchable(['name', 'sku'])
                    ->sortable()
                    ->description(fn (Product $record): ?string => $record->sku),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->sortable(),

                TextInputColumn::make('price')
                    ->label('Pris (kr)')
                    ->type('number')
                    ->rules(['required', 'numeric', 'min:0'])
                    ->sortable(),

                TextInputColumn::make('sale_price')
                    ->label('Reapris (kr)')
                    ->type('number')
                    ->rules(['nullable', 'numeric', 'min:0'])
                    ->placeholder('--')
                    ->sortable(),

                TextColumn::make('condition')
                    ->label('Skick')
                    ->badge(),

                TextColumn::make('variants_sum_stock')
                    ->label('Lager')
                    ->sum('variants', 'stock'),

                ToggleColumn::make('is_active')
                    ->label('Publicerad'),

                ToggleColumn::make('is_new')
                    ->label('Ny'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),

                TernaryFilter::make('is_active')
                    ->label('Publicerad'),

                TernaryFilter::make('is_new')
                    ->label('Ny inleverans'),
            ])
            ->recordActions([
                Action::make('duplicate')
                    ->label('Duplicera')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Duplicera vara')
                    ->modalDescription('Skapar en kopia (inkl. storlekar/lager) som du sedan redigerar. Kopian är dold från butiken tills du publicerar den.')
                    ->action(function (Product $record) {
                        // Built explicitly (not replicate()) so computed/
                        // aggregate attributes the table query attaches to
                        // $record (e.g. variants_sum_stock from the "Lager"
                        // column) never leak into the insert.
                        $copy = Product::create([
                            'category_id' => $record->category_id,
                            'sku' => $record->sku.'-KOPIA-'.Str::upper(Str::random(4)),
                            'name' => $record->name,
                            'description' => $record->description,
                            'price' => $record->price,
                            'sale_price' => $record->sale_price,
                            'condition' => $record->condition,
                            'image' => $record->image,
                            'is_new' => $record->is_new,
                            'is_active' => false,
                            'specs' => $record->specs,
                        ]);

                        foreach ($record->variants as $variant) {
                            $copy->variants()->create([
                                'size' => $variant->size,
                                'stock' => $variant->stock,
                            ]);
                        }

                        Notification::make()
                            ->title('Vara duplicerad')
                            ->body('Redigera kopian nedan -- den är dold tills du publicerar den.')
                            ->success()
                            ->send();

                        return redirect(static::getUrl('edit', ['record' => $copy]));
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('adjustPrice')
                        ->label('Justera pris')
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->color('warning')
                        ->form([
                            Radio::make('mode')
                                ->label('Typ av justering')
                                ->options([
                                    'percent' => 'Procent (%)',
                                    'fixed' => 'Fast belopp (kr)',
                                ])
                                ->default('percent')
                                ->required()
                                ->inline(),

                            TextInput::make('amount')
                                ->label('Värde')
                                ->numeric()
                                ->required()
                                ->helperText('Positivt = höjning, negativt = sänkning. T.ex. -15 för 15% rabatt, eller -50 för 50 kr rabatt. Gäller ordinarie pris (reapris rörs inte).'),
                        ])
                        ->action(function (array $data, Collection $records) {
                            foreach ($records as $product) {
                                $newPrice = $data['mode'] === 'percent'
                                    ? (int) round($product->price * (1 + ((float) $data['amount']) / 100))
                                    : (int) round($product->price + $data['amount']);

                                $product->update(['price' => max(0, $newPrice)]);
                            }

                            Notification::make()
                                ->title('Priser uppdaterade för '.$records->count().' varor')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation(),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
