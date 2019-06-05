(ns knossos-recruiterphp.model
  (:require [knossos.model :as model]
            [knossos.core :as core]
            [knossos.op :as op]
            [clojure.pprint :refer [pprint]])
  (:import knossos.model.Model))

(defrecord MongoLock [owner]
  Model
  (step [this op]
    (let [{:keys [value f process]} op]
      (condp = [(if owner :taken :free) f]
        [:taken :acquire] (model/inconsistent "acquired a taken lock")
        [:taken :release] (if (= owner process)
                            (MongoLock. nil)
                            (model/inconsistent "released another process's lock"))
        [:free :acquire] (MongoLock. process)
        [:free :release] (model/inconsistent "released a free lock")))))

(defn mongo-lock
  "A MongoLock.php abstract model responding to :acquire and :release messages"
  ([] (MongoLock. nil))
  ([owner] (MongoLock. owner)))
