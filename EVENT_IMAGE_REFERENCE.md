# Event Image Reference Guide

**Last Updated:** 2026-03-02
**Status:** Main aesthetic only (62 images)

Complete reference mapping all 62 events to their specific images for the "main" aesthetic.

---

## Overview

Event-specific images allow each event to have unique visual representation. This document serves as the reference guide for:
- Event ID to filename mapping
- Event description and context
- Image concept and visual direction
- Implementation status tracking

**Naming Convention:** `Event-{event_id}.jpg` (e.g., `Event-distress_signal.jpg`)

**Location:** `media/main/Event-{event_id}.jpg`

**Fallback:** If event-specific image missing, falls back to generic `Event-01.jpg` through `Event-04.jpg`

---

## Implementation Status

**Code Status:** ✅ Implemented in ViewManager.php (lines 1211-1234)
- Checks for event-specific image first
- Falls back to generic images if missing
- Logs warning in DEBUG_MODE for missing main aesthetic images

**Image Generation Status:** 🔄 Ready for pipeline
- 62 prompts generated in `img_event.json`
- Using "main" aesthetic style (realistic space imagery)
- Prompts follow cinematic, dramatic, realistic sci-fi aesthetic

---

## Event Categories

Events are grouped by theme for easier reference:

### Ship Emergencies & Crises
- distress_signal
- resource_shortage
- space_storm
- radiation_storm
- meteor_shower
- supply_leak
- space_dysentery
- chad_conflict
- uranus_pressure

### Derelict & Salvage
- mysterious_derelict
- derelict_ship
- abandoned_probe
- debris_field_salvage
- luxury_yacht_wreck

### Resource Opportunities
- ice_asteroid_mining
- comet_tail_harvest
- gas_giant_skim
- hydrogen_collector
- compressed_fuel_pods
- antimatter_containment
- deuterium_tanker
- rare_mineral_asteroid

### Food & Biology
- hydroponic_pod
- aquaponics_pod
- algae_farm_drift
- mushroom_cultivation
- protein_synthesizer

### Hostile Encounters
- space_pirates
- asteroid_field

### Discovery & Wonder
- mysterious_signal
- aurora_nebula
- earth_broadcast
- uranus_rings
- uranus_moons_confusion
- uranus_color_debate

### Trading & Commerce
- trading_post
- bounty_rescue
- solar_collector_drift
- data_cache
- entertainment_archive

### Rescue & Humanity
- chad_rescue_pod

### Crypto Satire Events
- memecoin_transmission
- uranus_gas_fees
- nft_derelict
- hodl_crisis
- memecoin_cargo_hold
- whale_manipulation
- liquidity_pool_drained
- uranus_colonist_naming
- wrong_planet
- paper_hands_mutiny
- yield_farming_disaster
- dao_governance
- smart_contract_bug
- shitcoin_payload

### Uranus Mission Humor
- uranus_probe_warning
- uranus_entry_angle
- uranus_retrograde
- uranus_smell
- uranus_tilt
- uranus_methane_oceans

### Reality-Breaking
- reality_breaking

---

## Complete Event Reference

### 1. distress_signal
**Title:** 🚨 DISTRESS SIGNAL 🚨
**Description:** Mining vessel stranded, offering supplies in exchange for rescue
**Image Filename:** `Event-distress_signal.jpg`
**Image Concept:** Damaged mining vessel drifting, distress beacon flashing red, industrial design, debris field, sense of desperation
**Visual Mood:** Dangerous opportunity, potential trap, dramatic rescue scenario

---

### 2. mysterious_derelict
**Title:** 🛸 MYSTERIOUS DERELICT 🛸
**Description:** Massive derelict with unknown technology, ancient and mysterious
**Image Filename:** `Event-mysterious_derelict.jpg`
**Image Concept:** Ancient alien/unknown vessel, geometric patterns, dark and imposing, electromagnetic corona, imposing scale
**Visual Mood:** Mystery, the unknown, ancient technology, unsettling discovery

---

### 3. chad_conflict
**Title:** ⚔️ CREW CONFLICT ⚔️
**Description:** Chad tensions escalating into heated argument affecting morale
**Image Filename:** `Event-chad_conflict.jpg`
**Image Concept:** Interior ship scene, two chad in confrontation, others watching concerned, interpersonal drama
**Visual Mood:** Human tension in isolation, confined space conflict, psychological pressure

---

### 4. resource_shortage
**Title:** ⚠️ RESOURCE SHORTAGE ⚠️
**Description:** Dangerously low supplies, life support struggling, malnutrition visible
**Image Filename:** `Event-resource_shortage.jpg`
**Image Concept:** Nearly empty supply bay, warning lights, sparse rations, chad examining meager resources
**Visual Mood:** Scarcity, desperation, tough choices, survival pressure

---

### 5. space_storm
**Title:** 🌪️ SPACE STORM 🌪️
**Description:** Massive electromagnetic storm with extreme readings approaching
**Image Filename:** `Event-space_storm.jpg`
**Image Concept:** Swirling plasma arcs, lightning-like energy discharges, ship buffeted by storm, violent cosmic phenomenon
**Visual Mood:** Raw cosmic power, danger, nature's fury in space

---

### 6. asteroid_field
**Title:** 🪨 Asteroid Field Detected!
**Description:** Dense asteroid field blocking path ahead
**Image Filename:** `Event-asteroid_field.jpg`
**Image Concept:** Tumbling asteroids of various sizes, navigational hazard, dramatic depth, mineral deposits visible
**Visual Mood:** Hazardous beauty, navigational challenge, spatial danger

---

### 7. space_dysentery
**Title:** 🤢 Medical Alert: Space Dysentery!
**Description:** Chad member contracted dysentery from contaminated water
**Image Filename:** `Event-space_dysentery.jpg`
**Image Concept:** Medical bay, chad in distress, contaminated water visible, clinical atmosphere
**Visual Mood:** Medical crisis, contamination concern, uncomfortable situation

---

### 8. derelict_ship
**Title:** 🛸 Derelict Cargo Ship Detected!
**Description:** Derelict cargo vessel drifting, appears abandoned
**Image Filename:** `Event-derelict_ship.jpg`
**Image Concept:** Dark abandoned freighter, cargo bay open, industrial design, no lights, eerie
**Visual Mood:** Abandonment mystery, salvage opportunity, eerie silence

---

### 9. radiation_storm
**Title:** ☢️ Radiation Storm Approaching!
**Description:** Massive solar flare sending radiation wave
**Image Filename:** `Event-radiation_storm.jpg`
**Image Concept:** Solar flare eruption, radiation shielding deploying, chad reacting with alarm, luminous wavefront
**Visual Mood:** Cosmic threat, protective response, natural danger

---

### 10. space_pirates
**Title:** 🏴‍☠️ Space Pirates Demand Tribute!
**Description:** Pirate vessel locked weapons, demanding cargo surrender
**Image Filename:** `Event-space_pirates.jpg`
**Image Concept:** Menacing pirate ship, weapons locked on, battle-damaged predatory vessel, tense standoff
**Visual Mood:** Immediate threat, vulnerability, hostile encounter

---

### 11. mysterious_signal
**Title:** 📡 Mysterious Signal Detected!
**Description:** Strange alien transmission from deep space
**Image Filename:** `Event-mysterious_signal.jpg`
**Image Concept:** Alien transmission on displays, complex patterns, unknown language, communications array, mysterious source
**Visual Mood:** First contact mystery, the unknown, otherworldly communication

---

### 12. meteor_shower
**Title:** ☄️ Meteor Shower Incoming!
**Description:** Hundreds of small meteors on collision course
**Image Filename:** `Event-meteor_shower.jpg`
**Image Concept:** Intense meteor swarm, point defense firing, defensive action, overwhelming incoming threats
**Visual Mood:** Immediate danger, defensive crisis, overwhelming assault

---

### 13. supply_leak
**Title:** 💧 Supply Leak Detected!
**Description:** Storage breach causing resources to vent into space
**Image Filename:** `Event-supply_leak.jpg`
**Image Concept:** Breached hull, resources venting (crystallizing water, food particles), EVA repair attempt, loss visible
**Visual Mood:** Resource loss, urgent repair, desperation to save supplies

---

### 14. trading_post
**Title:** 🛰️ Trading Post Beacon!
**Description:** Automated trading station offering goods exchange
**Image Filename:** `Event-trading_post.jpg`
**Image Concept:** Unmanned automated station, modular platform, commercial opportunity in deep space
**Visual Mood:** Commerce opportunity, space infrastructure, utilitarian function

---

### 15. uranus_probe_warning
**Title:** 🛰️ WARNING: Uranus Probe Required
**Description:** Mission Control insists on probing Uranus, chad uncomfortable with phrasing
**Image Filename:** `Event-uranus_probe_warning.jpg`
**Image Concept:** Mission Control feed with serious controllers, official directive, chad trying not to laugh
**Visual Mood:** Professional seriousness vs uncomfortable humor, bureaucratic awkwardness

---

### 16. memecoin_transmission
**Title:** 📈 Incoming Transmission: HOT COIN TIP
**Description:** Encrypted message promising 1000x gains on $URANUSCOIN
**Image Filename:** `Event-memecoin_transmission.jpg`
**Image Concept:** Garish crypto marketing on displays, rocket emojis, absurd charts, chad skeptical but tempted
**Visual Mood:** Obvious scam presented professionally, satirical crypto culture

---

### 17. uranus_gas_fees
**Title:** 💨 Uranus Gas Leak Detected
**Description:** Methane gas leak affecting operations, astronomical gas fees pun
**Image Filename:** `Event-uranus_gas_fees.jpg`
**Image Concept:** Uranus in background, gas detection displays mixed with blockchain 'gas fee' warnings, visual pun
**Visual Mood:** Scientific detection meets crypto terminology joke

---

### 18. nft_derelict
**Title:** 🖼️ NFT Cargo Ship Discovered
**Description:** Derelict carrying worthless Bored Ape Astronaut NFTs
**Image Filename:** `Event-nft_derelict.jpg`
**Image Concept:** Abandoned ship, cargo bay full of NFT displays, worthless digital art, satirical bubble commentary
**Visual Mood:** NFT bubble satire, worthless cargo, abandoned investment

---

### 19. uranus_rings
**Title:** 💍 Uranus Rings Detected
**Description:** Sensors detect 13 faint rings, onion rings joke doesn't land
**Image Filename:** `Event-uranus_rings.jpg`
**Image Concept:** Uranus with accurate faint ring system, chad making/groaning at onion rings joke, deadpan humor
**Visual Mood:** Accurate astronomy with dad joke overlay

---

### 20. hodl_crisis
**Title:** 💎 Chad Member Refuses to Sell $TITANICTOKEN
**Description:** Chad member down 99.8% but has diamond hands
**Image Filename:** `Event-hodl_crisis.jpg`
**Image Concept:** Chad member clutching tablet showing catastrophic loss, stubborn denial, diamond hand emoji
**Visual Mood:** Crypto culture satire, sunk cost fallacy, stubborn denial

---

### 21. uranus_entry_angle
**Title:** 🎯 Calculating Entry Angle Into Uranus
**Description:** Navigator calculating approach, double entendres excruciating
**Image Filename:** `Event-uranus_entry_angle.jpg`
**Image Concept:** Navigation displays with suggestive technical terms, chad maintaining professionalism despite phrasing
**Visual Mood:** Technical accuracy meets unavoidable innuendo

---

### 22. whale_manipulation
**Title:** 🐋 Space Whale Detected (Market Manipulation)
**Description:** Massive entity dumping tokens OR actual space whale
**Image Filename:** `Event-whale_manipulation.jpg`
**Image Concept:** Split scene: actual bioluminescent space whale AND market crash graph, dual meaning visual
**Visual Mood:** Surreal beauty meets financial chaos, ambiguous threat

---

### 23. uranus_retrograde
**Title:** 🔄 Uranus is in Retrograde
**Description:** Astrology officer insists retrograde matters (it doesn't)
**Image Filename:** `Event-uranus_retrograde.jpg`
**Image Concept:** Astrological displays overlaid on actual navigation, mysticism in pragmatic spacecraft
**Visual Mood:** Pseudoscience vs real science, skeptical chad, mystical aesthetic clash

---

### 24. shitcoin_payload
**Title:** 💩 Cargo Bay Inspection: Shitcoins Detected
**Description:** Cargo bay is 40% shitcoins chad trying to shill
**Image Filename:** `Event-shitcoin_payload.jpg`
**Image Concept:** Cargo containers full of worthless crypto merchandise, chad discovering useless payload
**Visual Mood:** Wasted cargo space, worthless merchandise, chad frustration

---

### 25. uranus_smell
**Title:** 👃 Sensors Detect Uranus Odor Profile
**Description:** Chemical analysis confirms Uranus smells like hydrogen sulfide
**Image Filename:** `Event-uranus_smell.jpg`
**Image Concept:** Scientific lab with H2S molecular displays, professional data, chad holding noses despite sealed ship
**Visual Mood:** Accurate science meets childish reactions

---

### 26. liquidity_pool_drained
**Title:** 🏊 ALERT: Liquid Oxygen Liquidity Pool Drained
**Description:** Someone bridged liquid oxygen to blockchain, it's gone
**Image Filename:** `Event-liquidity_pool_drained.jpg`
**Image Concept:** Life support emergency with DeFi interface showing oxygen 'bridged' to blockchain, chad panicking
**Visual Mood:** Serious emergency meets absurd crypto cause

---

### 27. uranus_colonist_naming
**Title:** 📝 Colonist Naming Rights NFT Drop
**Description:** Uranus Authority selling NFTs for colonist naming rights
**Image Filename:** `Event-uranus_colonist_naming.jpg`
**Image Concept:** Official NFT marketplace with colonist naming certificates, predatory monetization
**Visual Mood:** Legitimate government branding meets NFT marketplace

---

### 28. wrong_planet
**Title:** 🌙 TO THE MOON! Wait...
**Description:** Crypto bro navigator plotting course to moon instead of Uranus
**Image Filename:** `Event-wrong_planet.jpg`
**Image Concept:** Navigator trying to plot moon course, captain correcting toward Uranus, culture clash
**Visual Mood:** Crypto meme culture vs serious mission

---

### 29. uranus_pressure
**Title:** 🔩 Structural Warning: Uranus Pressure Extreme
**Description:** Hull experiencing intense atmospheric pressure, groaning audibly
**Image Filename:** `Event-uranus_pressure.jpg`
**Image Concept:** Ship hull visibly straining, stress on structure, Uranus's massive atmosphere looming
**Visual Mood:** Physical forces, engineering limits, hostile environment

---

### 30. yield_farming_disaster
**Title:** 🌾 Crop Yield Farming vs DeFi Yield Farming
**Description:** Engineer confused actual crops with DeFi, staked crops on blockchain
**Image Filename:** `Event-yield_farming_disaster.jpg`
**Image Concept:** Empty hydroponic garden, plants visible in DeFi interface, literal vs digital confusion
**Visual Mood:** Absurd misunderstanding, physical food now digital tokens

---

### 31. uranus_tilt
**Title:** ⚠️ Uranus Rotates Sideways (98° Axial Tilt)
**Description:** Uranus's extreme tilt affecting approach and worldview
**Image Filename:** `Event-uranus_tilt.jpg`
**Image Concept:** Uranus rotating sideways like a wheel, navigation displays showing unusual axis
**Visual Mood:** Astronomical oddity, bizarre planetary behavior

---

### 32. memecoin_cargo_hold
**Title:** 📦 Cargo Hold Inspection: Physical Memecoins
**Description:** 4 tons of physical Uranus-themed crypto merchandise loaded
**Image Filename:** `Event-memecoin_cargo_hold.jpg`
**Image Concept:** Cargo bay packed with boxes of crypto merch, wasted payload capacity, chad exasperated
**Visual Mood:** Worthless cargo weight, wasted space, poor decisions

---

### 33. dao_governance
**Title:** 🗳️ DAO VOTE: Ship Operations Now Decentralized
**Description:** Chad formed DAO, all decisions need governance votes now
**Image Filename:** `Event-dao_governance.jpg`
**Image Concept:** Ship controls taken over by voting interfaces, captain frustrated, bureaucratic nightmare
**Visual Mood:** Over-applied blockchain governance, command paralysis

---

### 34. uranus_moons_confusion
**Title:** 🌑 Uranus Has 27 Moons (This Seems Excessive)
**Description:** Navigation confused by 27 moons with impossible names
**Image Filename:** `Event-uranus_moons_confusion.jpg`
**Image Concept:** Navigation display cluttered with 27 labeled moons, navigator overwhelmed by complexity
**Visual Mood:** Accurate but overwhelming astronomy, too many celestial bodies

---

### 35. paper_hands_mutiny
**Title:** 📄 Paper Hands Mutiny in Progress
**Description:** Half chad wants to sell resources and return to Earth
**Image Filename:** `Event-paper_hands_mutiny.jpg`
**Image Concept:** Chad divided, paper hands vs diamond hands, crypto investor psychology in space
**Visual Mood:** Internal conflict, competing philosophies, divided chad

---

### 36. uranus_color_debate
**Title:** 🎨 Scientific Debate: What Color Is Uranus?
**Description:** Chad arguing about Uranus color (blue, cyan, blue-green), operations halted
**Image Filename:** `Event-uranus_color_debate.jpg`
**Image Concept:** Multiple displays showing Uranus in different color calibrations, scientists arguing pedantically
**Visual Mood:** Academic pettiness, pedantic debate, work stoppage

---

### 37. smart_contract_bug
**Title:** 🐛 CRITICAL: Smart Contract Bug in Life Support
**Description:** Life support on blockchain with reentrancy bug, oxygen draining infinitely
**Image Filename:** `Event-smart_contract_bug.jpg`
**Image Concept:** Life support panels showing code bug, oxygen dropping, engineer debugging blockchain
**Visual Mood:** Critical emergency with ridiculous cause, software crisis

---

### 38. uranus_methane_oceans
**Title:** 🌊 Discovery: Methane Oceans on Uranus
**Description:** Discovered liquid methane oceans (scientifically unlikely but happening)
**Image Filename:** `Event-uranus_methane_oceans.jpg`
**Image Concept:** Uranus with visible liquid methane seas, beautiful but scientifically wrong
**Visual Mood:** Impossible but stunning discovery, breaking known science

---

### 39. reality_breaking
**Title:** ⚠️ SYSTEM ERROR: REALITY.EXE HAS STOPPED WORKING
**Description:** Reality breaking down near Uranus, physics becoming optional
**Image Filename:** `Event-reality_breaking.jpg`
**Image Concept:** Space glitching, impossible geometry, reality errors visible, surreal breakdown
**Visual Mood:** Surreal, unsettling, reality corruption, breaking physics

---

### 40. ice_asteroid_mining
**Title:** 🧊 Ice Asteroid Detected!
**Description:** Water-rich ice asteroid for fuel and water extraction
**Image Filename:** `Event-ice_asteroid_mining.jpg`
**Image Concept:** Brilliant ice asteroid, mining equipment deployed, extraction in progress
**Visual Mood:** Resource opportunity, industrial extraction, practical salvage

---

### 41. abandoned_probe
**Title:** 🛰️ Abandoned Probe Detected!
**Description:** Old probe with potentially usable power cells
**Image Filename:** `Event-abandoned_probe.jpg`
**Image Concept:** Historical space probe (Voyager-style), tumbling slowly, nostalgic technology
**Visual Mood:** Historical salvage, nostalgia, ancient space program

---

### 42. debris_field_salvage
**Title:** 🪨 Debris Field Ahead!
**Description:** Wreckage from old ship, salvage could yield supplies
**Image Filename:** `Event-debris_field_salvage.jpg`
**Image Concept:** Scattered ship wreckage, identifiable components, salvage operation
**Visual Mood:** Destruction and opportunity, tragic salvage

---

### 43. comet_tail_harvest
**Title:** ☄️ Comet Tail Detected!
**Description:** Comet tail rich in hydrogen for fuel harvesting
**Image Filename:** `Event-comet_tail_harvest.jpg`
**Image Concept:** Magnificent comet with streaming tail, ship harvesting material, beautiful phenomenon
**Visual Mood:** Natural beauty meets practical opportunity, cosmic harvest

---

### 44. bounty_rescue
**Title:** 🆘 Distress Beacon Detected!
**Description:** Corporate vessel stranded offering rescue bounty
**Image Filename:** `Event-bounty_rescue.jpg`
**Image Concept:** Expensive corporate vessel in distress, professional appearance, potential profit
**Visual Mood:** Commercial rescue opportunity, well-funded target

---

### 45. solar_collector_drift
**Title:** ☀️ Damaged Solar Collectors Detected!
**Description:** Military-grade solar collectors for fuel conversion
**Image Filename:** `Event-solar_collector_drift.jpg`
**Image Concept:** Military solar panels, battle damaged, sophisticated technology, salvage opportunity
**Visual Mood:** Military technology salvage, advanced equipment

---

### 46. gas_giant_skim
**Title:** 🪐 Gas Giant Flyby Opportunity!
**Description:** Atmospheric skimming for massive fuel yield
**Image Filename:** `Event-gas_giant_skim.jpg`
**Image Concept:** Ship diving into gas giant atmosphere, scoops deployed, dangerous fuel collection
**Visual Mood:** Daring operation, massive scale, dangerous but lucrative

---

### 47. hydroponic_pod
**Title:** 🌱 Escaped Hydroponic Pod Detected!
**Description:** Commercial pod with growing vegetables inside
**Image Filename:** `Event-hydroponic_pod.jpg`
**Image Concept:** Transparent greenhouse pod, plants growing inside, fresh food rare in space
**Visual Mood:** Living cargo, fresh food opportunity, biological salvage

---

### 48. protein_synthesizer
**Title:** 🥩 Protein Synthesizer Cache Found!
**Description:** Equipment and materials for protein synthesis
**Image Filename:** `Event-protein_synthesizer.jpg`
**Image Concept:** Container with bioreactors and food production equipment, industrial food tech
**Visual Mood:** Food production opportunity, industrial resource

---

### 49. aurora_nebula
**Title:** ✨ Spectacular Aurora Nebula!
**Description:** Breathtaking nebula with aurora-like colors, hauntingly beautiful
**Image Filename:** `Event-aurora_nebula.jpg`
**Image Concept:** Flowing ribbons of colorful nebula, ship silhouetted, cosmic majesty
**Visual Mood:** Beauty and awe, no threat, natural wonder

---

### 50. earth_broadcast
**Title:** 📻 Signal from Earth Detected!
**Description:** Music broadcast from Earth (2020 hits)
**Image Filename:** `Event-earth_broadcast.jpg`
**Image Concept:** Chad listening to Earth music, emotional connection to home, nostalgic moment
**Visual Mood:** Connection across distance, emotional morale boost, nostalgia

---

### 51. data_cache
**Title:** 💾 Encrypted Data Cache Found!
**Description:** Data storage module, corporations pay for recovered data
**Image Filename:** `Event-data_cache.jpg`
**Image Concept:** Industrial data vault, encrypted, corporate markings, valuable information
**Visual Mood:** Information value, mystery, corporate interest

---

### 52. rare_mineral_asteroid
**Title:** 💎 Rare Mineral Asteroid Detected!
**Description:** Asteroid rich in platinum and rare earth elements
**Image Filename:** `Event-rare_mineral_asteroid.jpg`
**Image Concept:** Asteroid glittering with metallic ores, visible wealth, mineral deposits catching light
**Visual Mood:** Striking it rich, valuable discovery, mineral wealth

---

### 53. hydrogen_collector
**Title:** 💧 HYDROGEN COLLECTOR ARRAY 💧
**Description:** Abandoned hydrogen collection array with usable fuel
**Image Filename:** `Event-hydrogen_collector.jpg`
**Image Concept:** Industrial fuel collection infrastructure, storage tanks with contents, practical salvage
**Visual Mood:** Industrial opportunity, fuel resource, practical value

---

### 54. compressed_fuel_pods
**Title:** ⛽ COMPRESSED FUEL PODS ⛽
**Description:** Military fuel pods jettisoned from cargo ship, easy pickings
**Image Filename:** `Event-compressed_fuel_pods.jpg`
**Image Concept:** Neat formation of military fuel containers, pristine, disciplined arrangement
**Visual Mood:** Military salvage, clean opportunity, valuable fuel

---

### 55. antimatter_containment
**Title:** ⚡ ANTIMATTER CONTAINMENT POD ⚡
**Description:** Research antimatter pod, extreme danger and extreme value
**Image Filename:** `Event-antimatter_containment.jpg`
**Image Concept:** Heavily shielded pod, glowing containment field, WARNING symbols, extreme hazard
**Visual Mood:** Maximum danger and value, careful approach, high stakes

---

### 56. deuterium_tanker
**Title:** 🧪 DEUTERIUM TANKER 🧪
**Description:** Industrial tanker with premium fusion fuel, radiation risk
**Image Filename:** `Event-deuterium_tanker.jpg`
**Image Concept:** Large fuel tanker, heavy shielding, radiation warnings, hazardous but valuable
**Visual Mood:** Industrial hazard, premium fuel, radiation concern

---

### 57. aquaponics_pod
**Title:** 🌿 AQUAPONICS POD 🌿
**Description:** Fish and vegetable cultivation in zero-g, still operational
**Image Filename:** `Event-aquaponics_pod.jpg`
**Image Concept:** Bio-dome with fish tanks and plants, living system working, thriving biology
**Visual Mood:** Life in space, closed-loop system, living food source

---

### 58. algae_farm_drift
**Title:** 🦠 ALGAE FARM BIOREACTOR 🦠
**Description:** Protein-rich algae production, not appetizing but nutritious
**Image Filename:** `Event-algae_farm_drift.jpg`
**Image Concept:** Cylindrical tanks with green algae, industrial biology, unglamorous but practical
**Visual Mood:** Practical food security, utilitarian biology, functional ugliness

---

### 59. mushroom_cultivation
**Title:** 🍄 MUSHROOM CULTIVATION POD 🍄
**Description:** Research pod growing edible mushrooms, quick zero-g growth
**Image Filename:** `Event-mushroom_cultivation.jpg`
**Image Concept:** Research pod with mushroom cultivation racks, various species, unusual food source
**Visual Mood:** Biological research, unusual but nutritious, fungal agriculture

---

### 60. entertainment_archive
**Title:** 🎮 ENTERTAINMENT ARCHIVE 🎮
**Description:** Movies, music, games — morale boost for chad
**Image Filename:** `Event-entertainment_archive.jpg`
**Image Concept:** Data module with entertainment branding, chad celebrating, psychological value
**Visual Mood:** Morale boost, entertainment value, psychological relief

---

### 61. luxury_yacht_wreck
**Title:** 🛥️ LUXURY YACHT WRECKAGE 🛥️
**Description:** Wealthy yacht debris with gourmet food, valuables, tragic story
**Image Filename:** `Event-luxury_yacht_wreck.jpg`
**Image Concept:** Elegant wreckage, expensive materials, fine debris, luxury and loss
**Visual Mood:** Tragedy and opportunity, wealth in ruins, moral complexity

---

### 62. chad_rescue_pod
**Title:** 🆘 ESCAPE POD WITH SURVIVORS 🆘
**Description:** Survivors running out of air, cost resources but save lives
**Image Filename:** `Event-chad_rescue_pod.jpg`
**Image Concept:** Escape pod, survivors visible inside, beacon flashing, urgent rescue, moral choice
**Visual Mood:** Life-or-death urgency, humanity, moral imperative, rescue scenario

---

## Usage Guidelines

### For Image Generation
1. Use prompts from `img_event.json` as input to your pipeline
2. Generate all 62 images for main aesthetic
3. Place in `media/main/` with exact filenames: `Event-{event_id}.jpg`
4. Test in-game to verify correct images appear for each event

### For Testing
1. Trigger events in local game tester or production
2. Verify event-specific image appears (not generic Event-01.jpg)
3. Check logs in DEBUG_MODE for any missing image warnings
4. Test fallback behavior by temporarily removing an image

### For Future Aesthetics
When adding event-specific images to other aesthetics (anime, pixel, rick):
1. Follow same naming convention: `Event-{event_id}.jpg`
2. Place in appropriate aesthetic folder: `media/{aesthetic}/`
3. Adapt prompts to match aesthetic style
4. Code already supports all aesthetics automatically

---

## Technical Notes

**File Format:** JPG preferred (smaller file size, faster loading)
**Resolution:** 512x512 or 1024x1024 pixels
**File Size:** < 1MB recommended
**Color Space:** sRGB
**Quality:** High quality, cinematic compositions

**Code Location:** `bot/ViewManager.php` lines 1211-1234
**Cache Format:** `main:Event-{event_id}.jpg` (aesthetic-aware caching)
**Fallback Images:** `Event-01.jpg`, `Event-02.jpg`, `Event-03.jpg`, `Event-04.jpg`

---

## Related Documentation

- **Image System:** `docs/creative/IMAGE_SYSTEM.md`
- **Image Generation:** `docs/creative/IMAGE_GENERATION_INSTRUCTIONS.md`
- **Events Data:** `data/events.json`
- **Pipeline Input:** `img_event.json` (generated for pipeline)

---

**Status:** Ready for image generation pipeline
**Next Step:** Generate 62 images using `img_event.json` prompts
