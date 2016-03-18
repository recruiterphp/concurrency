(ns knossos-onebip.model-test
  (:require [clojure.test :refer :all]
            [knossos-onebip.model :refer :all]
            [knossos.model :refer :all]
            [knossos.core :as core]
            [knossos.op :refer :all]
            [clojure.java.io :as io]
            [clojure.pprint :refer [pprint]]
            [clojure-csv.core :refer [parse-csv]]))

(deftest free-state
  (let [lock (mongo-lock)]
    (is (= (mongo-lock :p1)
           (step lock (invoke :p1 :acquire nil))))
    (is (= (inconsistent "released a free lock")
           (step lock (invoke :p1 :release nil))))))

(deftest locked-state
  (let [lock (mongo-lock :p1)]
    (is (= (mongo-lock)
           (step lock (ok :p1 :release nil))))
    (is (= (inconsistent "released another process's lock")
           (step lock (ok :p2 :release nil))))
    (is (= (inconsistent "acquired a taken lock")
           (step lock (ok :p1 :acquire nil))))
    (is (= (inconsistent "acquired a taken lock")
           (step lock (ok :p2 :acquire nil))))))

(defn history-from-file [path]
  (let [lines (-> (io/file path)
                  io/reader
                  parse-csv)]
    (map (fn [[_ process type f]]
           {:type (keyword type)
            :process (keyword process)
            :f (keyword f)
            :value nil})
         lines)))

(defn sample-history []
  (let [lock (mongo-lock)
        history
          (history-from-file "/home/giorgio/provisioning2/mongolock_98.log")]
    (core/analysis lock history)))

; does this reorder the operations of a single process?
