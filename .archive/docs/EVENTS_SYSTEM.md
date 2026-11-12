# Events System Documentation

## Overview

The Occupy Uranus bot features a sophisticated event system that creates dynamic, interactive encounters during the space journey. Events provide meaningful choices that affect resources, chad morale, and story progression, making each playthrough unique and engaging.

## Key Features

- **Dual Event Types**: Regular events and enhanced events with chad interactions
- **Dynamic Choice System**: Multiple outcomes with probability-based results
- **Chad Template System**: Personalized events using chad names and roles
- **Repeatable Events**: Some events can occur multiple times
- **Resource Impact**: Events directly affect fuel, food, hull, chad, and morale
- **Story Integration**: Events weave narrative elements into gameplay

## Architecture

### Event Data Structure

Events are stored in two JSON files:

#### `data/events.json` - Regular Events
```json
{
  "events": [
    {
      "id": "asteroid_field",
      "title": "🪨 Asteroid Field Detected!",
      "description": "A dense asteroid field blocks your path ahead.",
      "repeatable": true,
      "choices": [
        {
          "id": "navigate",
          "text": "Navigate through (risky)",
          "outcomes": [
            {
              "probability": 0.5,
              "effects": {"hull": -20},
              "message": "💥 You took damage navigating the asteroids!"
            }
          ]
        }
      ]
    }
  ]
}
```

#### `data/enhanced_events.json` - Enhanced Events
```json
{
  "events": [
    {
      "id": "distress_signal",
      "title": "🚨 DISTRESS SIGNAL 🚨",
      "description": "Your comms crackle with a weak distress signal. {paranoid_chad_name} mutters about space pirates.",
      "repeatable": true,
      "chad_reactions": [
        "{engineer_name}: \"I've triple-checked everything. Still worried.\"",
        "{captain_name}: \"I've always wanted to see Uranus up close!\""
      ],
      "choices": [
        {
          "id": "investigate",
          "text": "Investigate and attempt rescue",
          "cost": "Fuel: -10",
          "risk": "Medium",
          "outcomes": [
            {
              "probability": 0.6,
              "effects": {"fuel": -10, "food": 15, "morale": 10},
              "message": "✅ SUCCESS: The chad was genuine.",
              "chad_reaction": "{engineer_name}: \"I told you it wasn't pirates.\""
            }
          ]
        }
      ]
    }
  ]
}
```

### Core Components

#### GameEngine.php
Main event processing logic:

- `getRandomEvent($session)` - Selects available events
- `processEventChoice($session, $eventId, $choiceId)` - Processes player choices
- `selectOutcome($outcomes)` - Probability-based outcome selection
- `applyEffects($session, $effects)` - Applies resource changes
- `getRandomEnhancedEvent($chad)` - Enhanced event with chad templates

#### Event Triggering
Events are triggered during day advancement:

```php
// Random event check in advanceDay()
$config = getDifficulty($session['difficulty']);
if (rand(1, 100) <= ($config['event_frequency'] * 100)) {
    $event = $this->getRandomEvent($session);
    if ($event) {
        $session['pending_event'] = $event['id'];
        return ['success' => true, 'event' => $event];
    }
}
```

## Event Types

### Regular Events
Standard events with basic choice/outcome structure:

**Features:**
- Simple choice/outcome pairs
- Direct resource effects
- Basic messaging
- Probability-based outcomes

**Example:**
```json
{
  "id": "space_dysentery",
  "title": "🤢 Medical Alert: Space Dysentery!",
  "description": "A chad has contracted space dysentery.",
  "repeatable": true,
  "choices": [
    {
      "id": "wait",
      "text": "Wait it out",
      "outcomes": [
        {
          "probability": 0.7,
          "effects": {"morale": -10},
          "message": "😌 The chad recovered."
        }
      ]
    }
  ]
}
```

### Enhanced Events
Advanced events with chad interactions and richer narratives:

**Features:**
- Chad template variables (`{chad_name}`, `{captain_name}`, etc.)
- Chad reactions and dialogue
- Cost and risk indicators
- Enhanced outcome formatting
- Chad-specific reactions to outcomes

**Example:**
```json
{
  "id": "chad_conflict",
  "title": "⚔️ CREW CONFLICT ⚔️",
  "description": "Tensions between {chad_name} and {paranoid_chad_name} have escalated.",
  "chad_reactions": [
    "{chad_name}: \"We need to take more risks!\"",
    "{paranoid_chad_name}: \"We need to be more careful!\""
  ],
  "choices": [
    {
      "id": "mediate_conflict",
      "text": "Mediate the conflict",
      "cost": "None",
      "risk": "None",
      "outcomes": [
        {
          "probability": 0.9,
          "effects": {"morale": 10},
          "message": "🤝 DIPLOMACY: Your mediation skills bring the chad together.",
          "chad_reaction": "{captain_name}: \"You handled that perfectly.\""
        }
      ]
    }
  ]
}
```

## Event Processing Flow

### 1. Event Triggering
Events are randomly triggered during day advancement:

```php
// Check if event should occur based on difficulty
$config = getDifficulty($session['difficulty']);
if (rand(1, 100) <= ($config['event_frequency'] * 100)) {
    $event = $this->getRandomEvent($session);
}
```

### 2. Event Selection
Available events are filtered based on completion status:

```php
// Filter events not already completed (unless repeatable)
$available = [];
foreach ($events as $event) {
    if (!in_array($event['id'], $session['events_completed']) || 
        ($event['repeatable'] ?? false)) {
        $available[] = $event;
    }
}
```

### 3. Event Display
Events are displayed with choices and chad reactions:

```php
// Enhanced event formatting
if (isset($event['chad_reactions'])) {
    $message = $this->formatEnhancedEvent($event, $userId, $username);
    $keyboard = $this->createEnhancedEventKeyboard($event);
}
```

### 4. Choice Processing
Player choices are processed with outcome selection:

```php
// Find the selected choice
$choice = null;
foreach ($event['choices'] as $c) {
    if ($c['id'] === $choiceId) {
        $choice = $c;
        break;
    }
}

// Select outcome based on probability
$outcome = $this->selectOutcome($choice['outcomes']);
```

### 5. Effect Application
Resource changes are applied to the session:

```php
// Apply effects to session
$this->applyEffects($session, $outcome['effects']);

// Add to completed events
$session['events_completed'][] = $eventId;
```

## Chad Template System

### Template Variables
Enhanced events use chad data for personalization:

- `{chad_name}` - Random chad name
- `{captain_name}` - Captain's name
- `{engineer_name}` - Engineer's name
- `{paranoid_chad_name}` - Paranoid chad name

### Template Replacement
Templates are replaced with actual chad data:

```php
public function replaceChadTemplates($text, $chad) {
    // Replace {chad_name} with random chad
    if (strpos($text, '{chad_name}') !== false) {
        $randomChad = $chad[array_rand($chad)];
        $text = str_replace('{chad_name}', $randomChad['name'], $text);
    }
    
    // Replace {captain_name} with captain
    if (strpos($text, '{captain_name}') !== false) {
        $captain = $this->findChadByRole($chad, 'Captain');
        $text = str_replace('{captain_name}', $captain['name'], $text);
    }
    
    return $text;
}
```

## Outcome System

### Probability-Based Selection
Outcomes are selected using weighted probability:

```php
private function selectOutcome($outcomes) {
    $rand = rand(1, 100) / 100;
    $cumulative = 0;
    
    foreach ($outcomes as $outcome) {
        $cumulative += $outcome['probability'];
        if ($rand <= $cumulative) {
            return $outcome;
        }
    }
    
    return $outcomes[0]; // Fallback
}
```

### Resource Effects
Events can affect multiple resources:

```php
private function applyEffects(&$session, $effects) {
    foreach ($effects as $resource => $change) {
        $session['resources'][$resource] += $change;
        
        // Clamp values to valid ranges
        $session['resources'][$resource] = max(0, $session['resources'][$resource]);
        
        if ($resource === 'hull' || $resource === 'morale') {
            $session['resources'][$resource] = min(100, $session['resources'][$resource]);
        }
    }
}
```

## Event Categories

### Resource Management Events
Events that affect ship resources:

- **Fuel Events**: `asteroid_field`, `radiation_storm`, `meteor_shower`
- **Food Events**: `space_dysentery`, `supply_leak`, `trading_post`
- **Hull Events**: `asteroid_field`, `space_pirates`, `meteor_shower`
- **Chad Events**: `space_dysentery`, `space_pirates`, `chad_conflict`

### Story Events
Events that advance narrative:

- **Discovery Events**: `mysterious_signal`, `derelict_ship`, `uranus_rings`
- **Chad Events**: `chad_conflict`, `paper_hands_mutiny`, `dao_governance`
- **Environmental Events**: `space_storm`, `uranus_pressure`, `uranus_tilt`

### Meme Events
Humorous events with crypto/space themes:

- **Crypto Events**: `memecoin_transmission`, `hodl_crisis`, `whale_manipulation`
- **Uranus Events**: `uranus_probe_warning`, `uranus_gas_fees`, `uranus_smell`
- **Meta Events**: `reality_breaking`, `smart_contract_bug`, `nft_derelict`

## Configuration

### Difficulty Settings
Event frequency varies by difficulty:

```php
// In config.php
define('DIFFICULTY_EASY', [
    'name' => 'Colonist',
    'event_frequency' => 0.2  // 20% chance per day
]);

define('DIFFICULTY_NORMAL', [
    'name' => 'Explorer',
    'event_frequency' => 0.3  // 30% chance per day
]);

define('DIFFICULTY_HARD', [
    'name' => 'Pioneer',
    'event_frequency' => 0.4  // 40% chance per day
]);
```

### Event Properties
Events can be configured with various properties:

- **`repeatable`**: Can the event occur multiple times?
- **`cost`**: Resource cost to make the choice
- **`risk`**: Risk level (Low, Medium, High)
- **`probability`**: Chance of each outcome (0.0 to 1.0)

## Event Creation Guide

### Creating Regular Events

1. **Define Event Structure**:
```json
{
  "id": "unique_event_id",
  "title": "🎯 Event Title",
  "description": "Event description text",
  "repeatable": true,
  "choices": []
}
```

2. **Add Choices**:
```json
{
  "id": "choice_id",
  "text": "Choice description",
  "outcomes": []
}
```

3. **Define Outcomes**:
```json
{
  "probability": 0.7,
  "effects": {"fuel": -10, "morale": 5},
  "message": "Outcome description"
}
```

### Creating Enhanced Events

1. **Add Chad Templates**:
```json
{
  "description": "Event involving {chad_name} and {captain_name}",
  "chad_reactions": [
    "{engineer_name}: \"Technical analysis complete.\"",
    "{paranoid_chad_name}: \"This seems suspicious.\""
  ]
}
```

2. **Add Cost and Risk**:
```json
{
  "id": "risky_choice",
  "text": "Take the risky path",
  "cost": "Fuel: -20",
  "risk": "High",
  "outcomes": []
}
```

3. **Add Chad Reactions to Outcomes**:
```json
{
  "probability": 0.6,
  "effects": {"hull": -15},
  "message": "The risky path caused damage.",
  "chad_reaction": "{engineer_name}: \"I warned you about this.\""
}
```

## Testing Events

### Manual Testing
Test events using debug commands:

```php
// Trigger specific event
$event = $this->getEventById('asteroid_field');
$session['pending_event'] = $event['id'];

// Test choice processing
$result = $this->processEventChoice($session, 'asteroid_field', 'navigate');
```

### Automated Testing
Create test scenarios:

```php
// Test outcome probabilities
$outcomes = [
    ['probability' => 0.5, 'effects' => ['fuel' => -10]],
    ['probability' => 0.5, 'effects' => ['hull' => -20]]
];

$results = [];
for ($i = 0; $i < 1000; $i++) {
    $outcome = $this->selectOutcome($outcomes);
    $results[] = $outcome;
}
```

## Performance Considerations

### Event Loading
Events are loaded with static caching:

```php
public function loadEvents() {
    static $events = null;
    if ($events === null) {
        $file = DATA_DIR . '/events.json';
        $events = json_decode(file_get_contents($file), true);
    }
    return $events;
}
```

### Memory Usage
- **Event data**: Loaded once per request
- **Chad templates**: Processed on-demand
- **Session data**: Minimal overhead for event tracking

### Scalability
- **Event count**: Currently ~50 events, easily scalable
- **Choice complexity**: No performance impact
- **Template processing**: Minimal overhead

## Troubleshooting

### Common Issues

**Events not triggering:**
- Check `event_frequency` in difficulty settings
- Verify event files exist and are valid JSON
- Check for pending events blocking advancement

**Choices not working:**
- Verify choice IDs match exactly
- Check outcome probability sums (should be ≤ 1.0)
- Ensure effects are valid resource names

**Chad templates not replacing:**
- Verify chad data exists in session
- Check template variable names match exactly
- Ensure chad roles are properly assigned

### Debug Commands

```php
// Check available events
$events = $this->loadEvents();
$available = array_filter($events, function($event) use ($session) {
    return !in_array($event['id'], $session['events_completed']) || 
           ($event['repeatable'] ?? false);
});

// Test event selection
$event = $this->getRandomEvent($session);
var_dump($event);

// Test choice processing
$result = $this->processEventChoice($session, $eventId, $choiceId);
var_dump($result);
```

## Future Enhancements

### Planned Features
- **Conditional Events**: Events that trigger based on game state
- **Event Chains**: Sequential events that build on each other
- **Dynamic Events**: Events that change based on previous choices
- **Event Images**: Visual representations for each event

### Extensibility
- **Custom Event Types**: Plugin system for new event categories
- **Event Modifiers**: Difficulty-based event modifications
- **Event Analytics**: Tracking event choices and outcomes
- **Event Editor**: Web interface for creating events

## API Reference

### GameEngine Methods

#### `getRandomEvent($session)`
Selects a random available event.

**Parameters:**
- `$session` - Current game session

**Returns:** Event data or null

#### `processEventChoice($session, $eventId, $choiceId)`
Processes a player's event choice.

**Parameters:**
- `$session` - Current game session (passed by reference)
- `$eventId` - ID of the event
- `$choiceId` - ID of the selected choice

**Returns:** Result array with success status and message

#### `getEventById($eventId)`
Retrieves event data by ID.

**Parameters:**
- `$eventId` - Event identifier

**Returns:** Event data or null

#### `getRandomEnhancedEvent($chad)`
Gets enhanced event with chad template replacement.

**Parameters:**
- `$chad` - Array of chad data

**Returns:** Enhanced event with templates replaced

### Event Data Schema

#### Regular Event
```json
{
  "id": "string",
  "title": "string",
  "description": "string",
  "repeatable": "boolean",
  "choices": [
    {
      "id": "string",
      "text": "string",
      "outcomes": [
        {
          "probability": "number",
          "effects": "object",
          "message": "string"
        }
      ]
    }
  ]
}
```

#### Enhanced Event
```json
{
  "id": "string",
  "title": "string",
  "description": "string",
  "repeatable": "boolean",
  "chad_reactions": ["string"],
  "choices": [
    {
      "id": "string",
      "text": "string",
      "cost": "string",
      "risk": "string",
      "outcomes": [
        {
          "probability": "number",
          "effects": "object",
          "message": "string",
          "chad_reaction": "string"
        }
      ]
    }
  ]
}
```

## Conclusion

The events system provides a rich, interactive experience that makes each journey to Uranus unique. The combination of regular and enhanced events, chad templates, and probability-based outcomes creates engaging gameplay that responds to player choices and chad dynamics.

The system is designed for extensibility, allowing easy addition of new events and event types while maintaining performance and reliability. The dual event system provides both simple, straightforward encounters and complex, narrative-driven experiences that enhance the overall game experience.
