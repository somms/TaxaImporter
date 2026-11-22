# IUCN Red List Provider

Este proveedor permite buscar y normalizar especies desde la IUCN Red List (Lista Roja de la UICN).

## Objetivo

Dado un nombre científico (o sinónimo), encontrar la especie aceptada en la IUCN Red List y obtener información sobre su estado de conservación.

## Arquitectura

El proveedor IUCN sigue el mismo patrón que el proveedor GBIF y consta de 4 clases principales:

### 1. IUCNSearchScrapper
**Responsabilidad:** Realizar scraping de la web pública de IUCN para buscar especies.

**Características:**
- Busca especies por nombre científico o sinónimo
- Extrae información básica: nombre científico, taxonid, categoría Red List, nombre común
- Maneja sinónimos: si el nombre buscado es un sinónimo, devuelve la especie aceptada
- Ordena resultados por relevancia (coincidencias exactas primero)

**Métodos principales:**
```php
public function search(string $query): array
```

### 2. IUCNSpecies
**Responsabilidad:** Coordinar la búsqueda entre el scraper y la API oficial de IUCN.

**Workflow:**
1. Usa `IUCNSearchScrapper` para encontrar la especie (maneja sinónimos)
2. Si se encuentra un taxonid y hay token de API, consulta la API para obtener datos completos
3. Combina datos del scraper y de la API
4. Retorna datos normalizados

**Configuración:**
- Requiere token de API de IUCN (opcional pero recomendado)
- Se configura mediante variable de entorno: `IUCN_API_TOKEN`
- Sin token, solo usa datos del scraper

**Métodos principales:**
```php
protected function queryRemoteSource(string $speciesName, string $author, $options = []): mixed
protected function queryApiByTaxonId(int $taxonid): ?array
protected function validMatch(): bool
```

### 3. IUCNSpeciesParser
**Responsabilidad:** Transformar los datos crudos de IUCN en una entidad `Species`.

**Características:**
- Extrae nombre científico, autor, nombre común
- Almacena categoría Red List (CR, EN, VU, NT, LC, DD, NE)
- Guarda información taxonómica (reino, filo, clase, orden, familia, género)
- Almacena fecha de evaluación y tendencia poblacional
- Incluye enlace a la página de IUCN

**Datos exportados:**
- `scientific_name`: Nombre científico aceptado
- `taxonid`: ID único de IUCN
- `category`: Categoría Red List
- `authority`: Autor del taxón
- `main_common_name`: Nombre común principal
- Taxonomía completa en `extra`

### 4. IUCNSpeciesProcessor
**Responsabilidad:** Integrar el workflow completo siguiendo el patrón de `SpeciesProcessor`.

**Características:**
- Procesa lotes de especies
- Consulta IUCN para cada especie
- Genera filas de salida con datos normalizados
- Maneja eventos del sistema

**Métodos principales:**
```php
public function getRemoteSpecies(Species $species): IUCNSpecies
public function getRemoteRow(Species $species): array
```

## Flujo de Negocio

### Búsqueda de Especies

```
Input: "Panthera leo" (o sinónimo)
    ↓
IUCNSearchScrapper::search()
    ↓
Scraping de https://www.iucnredlist.org/search?query=Panthera+leo
    ↓
Extrae: taxonid, nombre científico, categoría
    ↓
IUCNSpecies::queryApiByTaxonId() [si hay token]
    ↓
GET https://api.iucnredlist.org/api/v4/taxonredirect/{taxonid}
    ↓
Combina datos scraper + API
    ↓
IUCNSpeciesParser::parseInput()
    ↓
Output: Species con datos completos
```

### Manejo de Sinónimos

La API de IUCN **no ofrece búsqueda por sinónimos**, por eso el scraper es esencial:

1. Usuario busca "Felis leo" (sinónimo antiguo)
2. Scraper encuentra que "Felis leo" → "Panthera leo" (aceptado)
3. Se obtiene el taxonid de "Panthera leo"
4. Se consulta la API con ese taxonid
5. Se marca como `synonym: true` en los datos

## Uso

### Configuración Básica

```php
use Somms\BV2Observation\Provider\IUCN\IUCNSpeciesProcessor;

$processor = new IUCNSpeciesProcessor(
    $inputParser,
    $inputSource,
    $eventDispatcher,
    $options
);
```

### Configurar Token de API

```bash
# En .env o variable de entorno
IUCN_API_TOKEN=tu_token_aqui
```

Para obtener un token: https://api.iucnredlist.org/

### Ejemplo de Uso Directo

```php
use Somms\BV2Observation\Provider\IUCN\IUCNSpecies;

$species = new IUCNSpecies('Panthera leo', '', 'Linnaeus, 1758');
$parser = new IUCNSpeciesParser();

if ($species->populateFromRemote($parser)) {
    echo "Encontrado: " . $species->getRemoteScientificName() . "\n";
    echo "Categoría: " . $species->extra['iucn_category'] . "\n";
    echo "ID: " . $species->getRemoteId() . "\n";
}
```

## Datos de Salida

El método `getRemoteRow()` retorna:

```php
[
    'iucn_species_id' => '15951',
    'iucn_species_name' => 'Panthera leo',
    'iucn_category' => 'VU',  // Vulnerable
    'output_species_name_author' => 'Panthera leo (Linnaeus, 1758)',
    'iucn_assessment_date' => '2016-06-30',
    'iucn_population_trend' => 'decreasing',
    'iucn_link' => 'https://www.iucnredlist.org/species/15951/...'
]
```

## Categorías Red List

- **EX** - Extinct (Extinto)
- **EW** - Extinct in the Wild (Extinto en Estado Silvestre)
- **CR** - Critically Endangered (En Peligro Crítico)
- **EN** - Endangered (En Peligro)
- **VU** - Vulnerable
- **NT** - Near Threatened (Casi Amenazado)
- **LC** - Least Concern (Preocupación Menor)
- **DD** - Data Deficient (Datos Insuficientes)
- **NE** - Not Evaluated (No Evaluado)

## Limitaciones

1. **Scraping dependiente de estructura HTML**: Si IUCN cambia su HTML, el scraper puede fallar
2. **Rate limiting**: La API tiene límites de consultas (10,000/día con token gratuito)
3. **Sin token**: Solo datos básicos del scraper (sin detalles completos)
4. **Cobertura**: IUCN no tiene todas las especies (principalmente vertebrados y algunas plantas)

## Diferencias con GBIF

| Aspecto | GBIF | IUCN |
|---------|------|------|
| Cobertura | Todas las especies | Principalmente especies amenazadas |
| API búsqueda | Sí, por nombre | No, solo por ID |
| Scraping | No necesario | Esencial para sinónimos |
| Datos únicos | Taxonomía completa | Estado de conservación |
| Token API | No requerido | Recomendado |

## Mantenimiento

### Actualizar Selectores HTML

Si el scraper falla, revisar y actualizar los selectores en `IUCNSearchScrapper::parseSpeciesCard()`:

```php
// Selectores actuales
$cards = $doc->find('.species-result, .result-item, article.species, div[data-taxonid]');
$nameEl = $card->find('h3, h4, .scientific-name, strong.species-name, a.species-link');
$categoryEl = $card->find('.category, .rl-category, span.status, .assessment-status');
```

### Debugging

Activar logging en el constructor:

```php
$this->http = new Client([
    'timeout' => 15,
    'debug' => true  // Ver requests HTTP
]);
```

## Referencias

- IUCN Red List: https://www.iucnredlist.org/
- API v4 Documentation: https://api.iucnredlist.org/api-docs/index.html
- API Token: https://api.iucnredlist.org/
