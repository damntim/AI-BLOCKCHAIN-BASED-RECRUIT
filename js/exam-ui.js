// Exam UI helper — question display and forward-only navigation
const examUI = {
    currentIndex: 0,
    totalQuestions: 0,
    answers: [],

    init(total) {
        this.totalQuestions = total;
        this.answers = new Array(total).fill(null);
    },

    setAnswer(index, answer) {
        this.answers[index] = answer;
    },

    getAnswer(index) {
        return this.answers[index];
    },

    canGoNext() {
        return this.currentIndex < this.totalQuestions - 1;
    },

    isLast() {
        return this.currentIndex >= this.totalQuestions - 1;
    },

    next() {
        if (this.canGoNext()) {
            this.currentIndex++;
            return true;
        }
        return false;
    },

    getProgress() {
        return ((this.currentIndex + 1) / this.totalQuestions) * 100;
    },

    getAllAnswers() {
        return this.answers;
    }
};
