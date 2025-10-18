<?php

require __DIR__ . '/vendor/autoload.php';


// Charger les variables d'environnement

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);

$dotenv->load();


// Connexion à la base de données

try {

    $pdo = new PDO(

        "mysql:host=".$_ENV['DB_HOST'].";dbname=".$_ENV['DB_NAME'].";charset=utf8",

        $_ENV['DB_USER'],

        $_ENV['DB_PASS']

    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (Exception $e) {

    die("Erreur DB : " . $e->getMessage());

}


// Tableau de 100 questions françaises

$questions = [

    ["question"=>"Quelle est la capitale de la France ?","correct_answer"=>"Paris","incorrect_answers"=>["Lyon","Marseille","Toulouse"],"category"=>"Géographie","difficulty"=>"facile"],

    ["question"=>"Qui a peint la Joconde ?","correct_answer"=>"Léonard de Vinci","incorrect_answers"=>["Michel-Ange","Raphaël","Donatello"],"category"=>"Art","difficulty"=>"facile"],

    ["question"=>"Combien y a-t-il de continents sur Terre ?","correct_answer"=>"7","incorrect_answers"=>["5","6","8"],"category"=>"Géographie","difficulty"=>"facile"],

    ["question"=>"Quel est le symbole chimique de l'eau ?","correct_answer"=>"H2O","incorrect_answers"=>["O2","HO2","H2"],"category"=>"Science","difficulty"=>"facile"],

    ["question"=>"Quel est le plus grand océan du monde ?","correct_answer"=>"Pacifique","incorrect_answers"=>["Atlantique","Indien","Arctique"],"category"=>"Géographie","difficulty"=>"facile"],

    ["question"=>"Qui a écrit 'Les Misérables' ?","correct_answer"=>"Victor Hugo","incorrect_answers"=>["Émile Zola","Albert Camus","Gustave Flaubert"],"category"=>"Littérature","difficulty"=>"moyen"],

    ["question"=>"En quelle année l’homme a-t-il marché sur la Lune pour la première fois ?","correct_answer"=>"1969","incorrect_answers"=>["1965","1971","1975"],"category"=>"Histoire","difficulty"=>"moyen"],

    ["question"=>"Quelle est la planète la plus proche du Soleil ?","correct_answer"=>"Mercure","incorrect_answers"=>["Vénus","Mars","Jupiter"],"category"=>"Science","difficulty"=>"facile"],

    ["question"=>"Quel est le plus long fleuve de France ?","correct_answer"=>"La Loire","incorrect_answers"=>["La Seine","Le Rhône","La Garonne"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Qui a écrit 'Le Petit Prince' ?","correct_answer"=>"Antoine de Saint-Exupéry","incorrect_answers"=>["Jean de La Fontaine","Victor Hugo","Albert Camus"],"category"=>"Littérature","difficulty"=>"facile"],

    ["question"=>"Quelle est la langue officielle du Brésil ?","correct_answer"=>"Portugais","incorrect_answers"=>["Espagnol","Anglais","Français"],"category"=>"Géographie","difficulty"=>"facile"],

    ["question"=>"Qui a découvert la pénicilline ?","correct_answer"=>"Alexander Fleming","incorrect_answers"=>["Louis Pasteur","Marie Curie","Gregor Mendel"],"category"=>"Science","difficulty"=>"moyen"],

    ["question"=>"Quel est le symbole chimique de l'or ?","correct_answer"=>"Au","incorrect_answers"=>["Ag","Pb","Gd"],"category"=>"Science","difficulty"=>"moyen"],

    ["question"=>"Quel pays a remporté la Coupe du Monde 2018 ?","correct_answer"=>"France","incorrect_answers"=>["Croatie","Brésil","Allemagne"],"category"=>"Sport","difficulty"=>"facile"],

    ["question"=>"Quel est l’organe principal du système circulatoire ?","correct_answer"=>"Le cœur","incorrect_answers"=>["Le foie","Les poumons","Les reins"],"category"=>"Science","difficulty"=>"facile"],

    ["question"=>"Quelle ville est surnommée 'la ville lumière' ?","correct_answer"=>"Paris","incorrect_answers"=>["Lyon","Marseille","Nice"],"category"=>"Géographie","difficulty"=>"facile"],

    ["question"=>"Quel est l’élément chimique le plus léger ?","correct_answer"=>"Hydrogène","incorrect_answers"=>["Hélium","Oxygène","Carbone"],"category"=>"Science","difficulty"=>"moyen"],

    ["question"=>"Quelle est la capitale de l’Australie ?","correct_answer"=>"Canberra","incorrect_answers"=>["Sydney","Melbourne","Brisbane"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Qui a composé la 9e symphonie ?","correct_answer"=>"Ludwig van Beethoven","incorrect_answers"=>["Mozart","Bach","Chopin"],"category"=>"Musique","difficulty"=>"difficile"],

    ["question"=>"Quel est le pays le plus peuplé du monde ?","correct_answer"=>"Chine","incorrect_answers"=>["Inde","États-Unis","Indonésie"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Quel est le plus grand désert chaud du monde ?","correct_answer"=>"Sahara","incorrect_answers"=>["Gobi","Kalahari","Atacama"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Qui a écrit 'Candide' ?","correct_answer"=>"Voltaire","incorrect_answers"=>["Rousseau","Diderot","Montesquieu"],"category"=>"Littérature","difficulty"=>"moyen"],

    ["question"=>"Quel est le plus grand mammifère terrestre ?","correct_answer"=>"Éléphant d'Afrique","incorrect_answers"=>["Rhinocéros","Hippopotame","Girafe"],"category"=>"Science","difficulty"=>"facile"],

    ["question"=>"Quel est le symbole chimique du fer ?","correct_answer"=>"Fe","incorrect_answers"=>["F","Fr","Fi"],"category"=>"Science","difficulty"=>"moyen"],

    ["question"=>"En quelle année est tombé le mur de Berlin ?","correct_answer"=>"1989","incorrect_answers"=>["1987","1991","1985"],"category"=>"Histoire","difficulty"=>"moyen"],

    ["question"=>"Quel pays est surnommé 'Le pays du Soleil Levant' ?","correct_answer"=>"Japon","incorrect_answers"=>["Chine","Corée du Sud","Thaïlande"],"category"=>"Géographie","difficulty"=>"facile"],

    ["question"=>"Quel est le plus haut sommet du monde ?","correct_answer"=>"Everest","incorrect_answers"=>["K2","Kangchenjunga","Makalu"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Qui a écrit 'Roméo et Juliette' ?","correct_answer"=>"William Shakespeare","incorrect_answers"=>["Victor Hugo","Molière","Voltaire"],"category"=>"Littérature","difficulty"=>"facile"],

    ["question"=>"Quelle est la formule chimique du sel de table ?","correct_answer"=>"NaCl","incorrect_answers"=>["KCl","Na2SO4","NaOH"],"category"=>"Science","difficulty"=>"facile"],

    ["question"=>"Quelle est la capitale de l'Espagne ?","correct_answer"=>"Madrid","incorrect_answers"=>["Barcelone","Valence","Séville"],"category"=>"Géographie","difficulty"=>"facile"],

    ["question"=>"Qui a peint 'La Nuit étoilée' ?","correct_answer"=>"Vincent van Gogh","incorrect_answers"=>["Claude Monet","Pablo Picasso","Paul Cézanne"],"category"=>"Art","difficulty"=>"moyen"],

    ["question"=>"Quel est le plus grand lac d'Afrique ?","correct_answer"=>"Lac Victoria","incorrect_answers"=>["Lac Tanganyika","Lac Malawi","Lac Tchad"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Qui a écrit 'Le Rouge et le Noir' ?","correct_answer"=>"Stendhal","incorrect_answers"=>["Balzac","Flaubert","Hugo"],"category"=>"Littérature","difficulty"=>"moyen"],

    ["question"=>"Quel est le plus grand océan après le Pacifique ?","correct_answer"=>"Atlantique","incorrect_answers"=>["Indien","Arctique","Antarctique"],"category"=>"Géographie","difficulty"=>"facile"],

    ["question"=>"Quel est le principal gaz à effet de serre ?","correct_answer"=>"Dioxyde de carbone","incorrect_answers"=>["Méthane","Oxygène","Azote"],"category"=>"Science","difficulty"=>"facile"],

    ["question"=>"Qui a inventé l'ampoule électrique ?","correct_answer"=>"Thomas Edison","incorrect_answers"=>["Nikola Tesla","Alexander Graham Bell","James Watt"],"category"=>"Science","difficulty"=>"facile"],

    ["question"=>"Quel pays a la plus grande population francophone ?","correct_answer"=>"France","incorrect_answers"=>["Canada","Belgique","Suisse"],"category"=>"Géographie","difficulty"=>"facile"],

    ["question"=>"Quel est le plus grand État des États-Unis par superficie ?","correct_answer"=>"Alaska","incorrect_answers"=>["Texas","Californie","Montana"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Quelle est la capitale du Canada ?","correct_answer"=>"Ottawa","incorrect_answers"=>["Toronto","Montréal","Vancouver"],"category"=>"Géographie","difficulty"=>"facile"],

    ["question"=>"Quel est le plus grand pays du monde ?","correct_answer"=>"Russie","incorrect_answers"=>["Canada","Chine","États-Unis"],"category"=>"Géographie","difficulty"=>"facile"],

    ["question"=>"Qui a écrit 'La Métamorphose' ?","correct_answer"=>"Franz Kafka","incorrect_answers"=>["Albert Camus","Victor Hugo","Molière"],"category"=>"Littérature","difficulty"=>"difficile"],

    ["question"=>"Quel est le plus long fleuve du monde ?","correct_answer"=>"Nil","incorrect_answers"=>["Amazone","Yangzi","Mississippi"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Quelle est la capitale de l'Italie ?","correct_answer"=>"Rome","incorrect_answers"=>["Milan","Florence","Naples"],"category"=>"Géographie","difficulty"=>"facile"],

    ["question"=>"Quel est l'élément chimique le plus abondant dans l'univers ?","correct_answer"=>"Hydrogène","incorrect_answers"=>["Oxygène","Hélium","Carbone"],"category"=>"Science","difficulty"=>"moyen"],

    ["question"=>"Qui a peint 'Guernica' ?","correct_answer"=>"Pablo Picasso","incorrect_answers"=>["Salvador Dalí","Claude Monet","Vincent van Gogh"],"category"=>"Art","difficulty"=>"moyen"],

    ["question"=>"Quel est le plus grand pays d'Amérique du Sud ?","correct_answer"=>"Brésil","incorrect_answers"=>["Argentine","Chili","Pérou"],"category"=>"Géographie","difficulty"=>"facile"],

    ["question"=>"Qui a écrit 'Germinal' ?","correct_answer"=>"Émile Zola","incorrect_answers"=>["Victor Hugo","Balzac","Flaubert"],"category"=>"Littérature","difficulty"=>"moyen"],

    ["question"=>"Quel est le plus grand désert froid du monde ?","correct_answer"=>"Antarctique","incorrect_answers"=>["Arctique","Gobi","Sibérie"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Quel est le plus grand volcan actif du monde ?","correct_answer"=>"Mauna Loa","incorrect_answers"=>["Etna","Kilauea","Vésuve"],"category"=>"Science","difficulty"=>"moyen"],

    ["question"=>"Qui a découvert l'Amérique en 1492 ?","correct_answer"=>"Christophe Colomb","incorrect_answers"=>["Vasco de Gama","Ferdinand Magellan","Amerigo Vespucci"],"category"=>"Histoire","difficulty"=>"facile"],

    ["question"=>"Quel est le plus petit pays du monde ?","correct_answer"=>"Vatican","incorrect_answers"=>["Monaco","San Marin","Liechtenstein"],"category"=>"Géographie","difficulty"=>"facile"],

    ["question"=>"Qui a écrit 'La Peste' ?","correct_answer"=>"Albert Camus","incorrect_answers"=>["Jean-Paul Sartre","Victor Hugo","Stendhal"],"category"=>"Littérature","difficulty"=>"moyen"],

    ["question"=>"Quel est le plus grand lac d'Amérique du Nord ?","correct_answer"=>"Lac Supérieur","incorrect_answers"=>["Lac Michigan","Lac Huron","Lac Ontario"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Quel est le plus grand état d'Australie ?","correct_answer"=>"Queensland","incorrect_answers"=>["Nouvelle-Galles du Sud","Victoria","Australie-Méridionale"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Qui a inventé le téléphone ?","correct_answer"=>"Alexander Graham Bell","incorrect_answers"=>["Thomas Edison","Nikola Tesla","Guglielmo Marconi"],"category"=>"Science","difficulty"=>"moyen"],

    ["question"=>"Quelle est la capitale de la Chine ?","correct_answer"=>"Pékin","incorrect_answers"=>["Shanghai","Hong Kong","Guangzhou"],"category"=>"Géographie","difficulty"=>"facile"],

    ["question"=>"Qui a écrit 'Les Fleurs du Mal' ?","correct_answer"=>"Charles Baudelaire","incorrect_answers"=>["Victor Hugo","Paul Verlaine","Arthur Rimbaud"],"category"=>"Littérature","difficulty"=>"moyen"],

    ["question"=>"Quel est le plus grand État du Brésil ?","correct_answer"=>"Amazonas","incorrect_answers"=>["São Paulo","Minas Gerais","Bahia"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Quelle est la capitale de la Russie ?","correct_answer"=>"Moscou","incorrect_answers"=>["Saint-Pétersbourg","Novossibirsk","Kazan"],"category"=>"Géographie","difficulty"=>"facile"],

    ["question"=>"Qui a inventé l'aviation moderne ?","correct_answer"=>"Les frères Wright","incorrect_answers"=>["Leonardo da Vinci","Alberto Santos-Dumont","Otto Lilienthal"],"category"=>"Science","difficulty"=>"moyen"],

    ["question"=>"Quel est le plus grand fleuve d'Amérique du Sud ?","correct_answer"=>"Amazone","incorrect_answers"=>["Orénoque","Paraná","São Francisco"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Qui a écrit 'Madame Bovary' ?","correct_answer"=>"Gustave Flaubert","incorrect_answers"=>["Émile Zola","Honoré de Balzac","Victor Hugo"],"category"=>"Littérature","difficulty"=>"moyen"],

    ["question"=>"Quel est le plus grand pays d'Afrique ?","correct_answer"=>"Algérie","incorrect_answers"=>["Soudan","Libye","République Démocratique du Congo"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Qui a inventé la radio ?","correct_answer"=>"Guglielmo Marconi","incorrect_answers"=>["Nikola Tesla","Alexander Graham Bell","Thomas Edison"],"category"=>"Science","difficulty"=>"moyen"],

    ["question"=>"Quel est le plus grand océan après l'Atlantique ?","correct_answer"=>"Indien","incorrect_answers"=>["Pacifique","Arctique","Antarctique"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Qui a peint 'Les Tournesols' ?","correct_answer"=>"Vincent van Gogh","incorrect_answers"=>["Claude Monet","Paul Cézanne","Paul Gauguin"],"category"=>"Art","difficulty"=>"moyen"],

    ["question"=>"Quel est le plus haut sommet d'Europe ?","correct_answer"=>"Mont Blanc","incorrect_answers"=>["Mont Elbrouz","Matterhorn","Grossglockner"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Qui a écrit 'Le Père Goriot' ?","correct_answer"=>"Honoré de Balzac","incorrect_answers"=>["Stendhal","Flaubert","Victor Hugo"],"category"=>"Littérature","difficulty"=>"moyen"],

    ["question"=>"Quel est le plus grand pays d'Océanie ?","correct_answer"=>"Australie","incorrect_answers"=>["Papouasie-Nouvelle-Guinée","Nouvelle-Zélande","Fidji"],"category"=>"Géographie","difficulty"=>"facile"],

    ["question"=>"Qui a inventé le moteur à combustion interne ?","correct_answer"=>"Nikolaus Otto","incorrect_answers"=>["Gottlieb Daimler","Karl Benz","James Watt"],"category"=>"Science","difficulty"=>"moyen"],

    ["question"=>"Quelle est la capitale de l'Allemagne ?","correct_answer"=>"Berlin","incorrect_answers"=>["Munich","Hambourg","Francfort"],"category"=>"Géographie","difficulty"=>"facile"],

    ["question"=>"Qui a écrit 'La Chartreuse de Parme' ?","correct_answer"=>"Stendhal","incorrect_answers"=>["Balzac","Flaubert","Victor Hugo"],"category"=>"Littérature","difficulty"=>"moyen"],

    ["question"=>"Quel est le plus grand lac d'Europe ?","correct_answer"=>"Lac Léman","incorrect_answers"=>["Lac de Constance","Lac Balaton","Lac Vänern"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Qui a inventé l’électricité moderne ?","correct_answer"=>"Michael Faraday","incorrect_answers"=>["Nikola Tesla","Thomas Edison","James Watt"],"category"=>"Science","difficulty"=>"moyen"],

    ["question"=>"Quel est le plus grand pays d'Amérique centrale ?","correct_answer"=>"Nicaragua","incorrect_answers"=>["Honduras","Guatemala","Costa Rica"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Qui a écrit 'La Divine Comédie' ?","correct_answer"=>"Dante Alighieri","incorrect_answers"=>["Pétrarque","Boccace","Chaucer"],"category"=>"Littérature","difficulty"=>"difficile"],

    ["question"=>"Quel est le plus grand pays d'Asie ?","correct_answer"=>"Russie","incorrect_answers"=>["Chine","Inde","Kazakhstan"],"category"=>"Géographie","difficulty"=>"moyen"],

    ["question"=>"Qui a inventé la vaccination ?","correct_answer"=>"Edward Jenner","incorrect_answers"=>["Louis Pasteur","Alexander Fleming","Robert Koch"],"category"=>"Science","difficulty"=>"moyen"],

];



// Ajouter les questions dans la BDD

foreach ($questions as $q) {

    $stmtCheck = $pdo->prepare("SELECT id FROM questions WHERE question = ?");

    $stmtCheck->execute([$q['question']]);

    if ($stmtCheck->fetch()) continue;


    $stmt = $pdo->prepare(

        "INSERT INTO questions (question, correct_answer, incorrect_answers, category, difficulty)

        VALUES (?, ?, ?, ?, ?)"

    );

    $stmt->execute([

        $q['question'],

        $q['correct_answer'],

        json_encode($q['incorrect_answers'], JSON_UNESCAPED_UNICODE),

        $q['category'],

        $q['difficulty']

    ]);


    echo "Question ajoutée : " . $q['question'] . "\n";

}


echo "Toutes les questions ont été ajoutées !\n"; 